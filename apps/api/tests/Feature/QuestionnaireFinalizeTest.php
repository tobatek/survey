<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\Question;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\QuestionnaireSection;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuestionnaireFinalizeTest extends TestCase
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
    public function validate_reports_errors_for_empty_questionnaire(): void
    {
        $questionnaire = Questionnaire::factory()->create();

        $response = $this->actingAs($this->admin)->getJson("/api/questionnaires/{$questionnaire->id}/validate");

        $response->assertOk();
        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('no_sections', $codes);
        $this->assertContains('no_questions', $codes);
    }

    #[Test]
    public function finalize_fails_with_422_when_structure_invalid(): void
    {
        $questionnaire = Questionnaire::factory()->create(['lock_version' => 1]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/questionnaires/{$questionnaire->id}/finalize", [], ['If-Match' => '1']);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'questionnaire_not_valid');
        $this->assertSame(Questionnaire::STATUS_DRAFT, $questionnaire->fresh()->status);
    }

    #[Test]
    public function finalize_succeeds_and_creates_immutable_snapshot(): void
    {
        $questionnaire = Questionnaire::factory()->create(['lock_version' => 1]);
        $section = QuestionnaireSection::factory()->create(['questionnaire_id' => $questionnaire->id]);
        Question::factory()->create([
            'questionnaire_id' => $questionnaire->id,
            'section_id' => $section->id,
            'type' => 'short_text',
            'required' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/questionnaires/{$questionnaire->id}/finalize", [], ['If-Match' => '1']);

        $response->assertOk();
        $response->assertJsonPath('data.status', Questionnaire::STATUS_READY);
        $response->assertJsonPath('data.is_editable', false);

        $fresh = $questionnaire->fresh();
        $this->assertNotNull($fresh->snapshot_checksum);
        $this->assertDatabaseHas('questionnaire_snapshots', [
            'questionnaire_id' => $questionnaire->id,
            'checksum' => $fresh->snapshot_checksum,
        ]);

        // Sudah ready -> tidak bisa diedit lagi lewat CRUD biasa.
        $this->actingAs($this->admin)
            ->postJson("/api/questionnaires/{$questionnaire->id}/sections", ['code' => 'S2', 'title' => 'X'], ['If-Match' => (string) $fresh->lock_version])
            ->assertStatus(422);
    }

    #[Test]
    public function finalize_with_stale_if_match_returns_409(): void
    {
        $questionnaire = Questionnaire::factory()->create(['lock_version' => 4]);

        $this->actingAs($this->admin)
            ->postJson("/api/questionnaires/{$questionnaire->id}/finalize", [], ['If-Match' => '1'])
            ->assertStatus(409);
    }

    #[Test]
    public function transition_from_ready_to_active_then_paused_then_closed(): void
    {
        $questionnaire = Questionnaire::factory()->create(['status' => Questionnaire::STATUS_READY, 'lock_version' => 1]);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/questionnaires/{$questionnaire->id}/transition",
            ['status' => 'active'],
            ['If-Match' => '1'],
        );
        $response->assertOk();
        $response->assertJsonPath('data.status', 'active');

        $response = $this->actingAs($this->admin)->postJson(
            "/api/questionnaires/{$questionnaire->id}/transition",
            ['status' => 'paused'],
            ['If-Match' => '2'],
        );
        $response->assertOk();
        $response->assertJsonPath('data.status', 'paused');

        $response = $this->actingAs($this->admin)->postJson(
            "/api/questionnaires/{$questionnaire->id}/transition",
            ['status' => 'closed'],
            ['If-Match' => '3'],
        );
        $response->assertOk();
        $response->assertJsonPath('data.status', 'closed');
    }

    #[Test]
    public function closed_questionnaire_cannot_transition_back_to_active(): void
    {
        $questionnaire = Questionnaire::factory()->create(['status' => Questionnaire::STATUS_CLOSED, 'lock_version' => 1]);

        $this->actingAs($this->admin)
            ->postJson("/api/questionnaires/{$questionnaire->id}/transition", ['status' => 'active'], ['If-Match' => '1'])
            ->assertStatus(422);
    }
}
