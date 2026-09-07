<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuestionnaireAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    #[Test]
    public function a_respondent_cannot_list_questionnaires(): void
    {
        $respondent = User::factory()->create();
        $respondent->roles()->attach(Role::query()->where('code', Role::STUDENT_RESPONDENT)->first());

        $this->actingAs($respondent)->getJson('/api/questionnaires')->assertForbidden();
    }

    #[Test]
    public function an_executive_viewer_can_list_but_not_create(): void
    {
        Questionnaire::factory()->create();

        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::query()->where('code', Role::EXECUTIVE_VIEWER)->first());

        $this->actingAs($viewer)->getJson('/api/questionnaires')->assertOk();
        $this->actingAs($viewer)->postJson('/api/questionnaires', [
            'code' => 'X-1', 'title' => 'X', 'audience_type' => 'student', 'identity_mode' => 'anonymous',
        ])->assertForbidden();
    }

    #[Test]
    public function an_lpm_admin_can_create_a_questionnaire(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $response = $this->actingAs($admin)->postJson('/api/questionnaires', [
            'code' => 'SKM-2026',
            'title' => 'Survei Kepuasan Mahasiswa 2026',
            'audience_type' => 'student',
            'identity_mode' => 'anonymous',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.lock_version', 1);
        $this->assertDatabaseHas('questionnaires', ['code' => 'SKM-2026', 'created_by' => $admin->id]);
    }

    #[Test]
    public function an_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/questionnaires')->assertUnauthorized();
    }
}
