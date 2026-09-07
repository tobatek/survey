<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\QuestionnaireSection;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuestionnaireCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());
    }

    #[Test]
    public function updating_with_correct_if_match_succeeds_and_bumps_lock_version(): void
    {
        $questionnaire = Questionnaire::factory()->create(['lock_version' => 1]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/questionnaires/{$questionnaire->id}", ['title' => 'Judul Baru'], ['If-Match' => '1']);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Judul Baru');
        $response->assertJsonPath('data.lock_version', 2);
    }

    #[Test]
    public function updating_with_stale_if_match_returns_409(): void
    {
        $questionnaire = Questionnaire::factory()->create(['lock_version' => 3]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/questionnaires/{$questionnaire->id}", ['title' => 'Judul Baru'], ['If-Match' => '1']);

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'lock_conflict');
        $this->assertDatabaseHas('questionnaires', ['id' => $questionnaire->id, 'title' => $questionnaire->title]);
    }

    #[Test]
    public function updating_without_if_match_header_returns_409(): void
    {
        $questionnaire = Questionnaire::factory()->create(['lock_version' => 1]);

        $this->actingAs($this->admin)
            ->patchJson("/api/questionnaires/{$questionnaire->id}", ['title' => 'Judul Baru'])
            ->assertStatus(409);
    }

    #[Test]
    public function a_draft_questionnaire_can_be_deleted(): void
    {
        $questionnaire = Questionnaire::factory()->create(['status' => Questionnaire::STATUS_DRAFT]);

        $this->actingAs($this->admin)->deleteJson("/api/questionnaires/{$questionnaire->id}")->assertNoContent();
        $this->assertDatabaseMissing('questionnaires', ['id' => $questionnaire->id]);
    }

    #[Test]
    public function a_ready_questionnaire_cannot_be_edited_or_deleted(): void
    {
        $questionnaire = Questionnaire::factory()->create(['status' => Questionnaire::STATUS_READY, 'lock_version' => 1]);

        $this->actingAs($this->admin)
            ->patchJson("/api/questionnaires/{$questionnaire->id}", ['title' => 'Coba Edit'], ['If-Match' => '1'])
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->deleteJson("/api/questionnaires/{$questionnaire->id}")
            ->assertStatus(422);
    }

    #[Test]
    public function crud_section_and_question_bumps_parent_lock_version(): void
    {
        $questionnaire = Questionnaire::factory()->create(['lock_version' => 1]);

        $sectionResponse = $this->actingAs($this->admin)->postJson(
            "/api/questionnaires/{$questionnaire->id}/sections",
            ['code' => 'S1', 'title' => 'Bagian 1'],
            ['If-Match' => '1'],
        );
        $sectionResponse->assertCreated();
        $this->assertSame(2, $questionnaire->fresh()->lock_version);

        $sectionId = $sectionResponse->json('data.id');

        $questionResponse = $this->actingAs($this->admin)->postJson(
            "/api/sections/{$sectionId}/questions",
            ['code' => 'Q1', 'type' => 'short_text', 'text' => 'Bagaimana pendapat Anda?'],
            ['If-Match' => '2'],
        );
        $questionResponse->assertCreated();
        $this->assertSame(3, $questionnaire->fresh()->lock_version);
    }

    #[Test]
    public function section_mutation_with_stale_lock_is_rejected(): void
    {
        $questionnaire = Questionnaire::factory()->create(['lock_version' => 5]);
        $section = QuestionnaireSection::factory()->create(['questionnaire_id' => $questionnaire->id]);

        $this->actingAs($this->admin)
            ->patchJson("/api/sections/{$section->id}", ['title' => 'Baru'], ['If-Match' => '1'])
            ->assertStatus(409);
    }
}
