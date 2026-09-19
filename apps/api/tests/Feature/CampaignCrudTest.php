<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['tenant_id' => 'default']);
        $this->admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());
    }

    #[Test]
    public function a_campaign_can_be_created_from_a_ready_questionnaire(): void
    {
        $questionnaire = Questionnaire::factory()->create([
            'tenant_id' => 'default',
            'status' => Questionnaire::STATUS_READY,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/questionnaires/{$questionnaire->id}/campaigns");

        $response->assertCreated();
        $response->assertJsonPath('data.questionnaire_id', $questionnaire->id);
        $response->assertJsonPath('data.status', Campaign::STATUS_DRAFT);
        $this->assertDatabaseHas('campaigns', [
            'questionnaire_id' => $questionnaire->id,
            'tenant_id' => 'default',
            'status' => Campaign::STATUS_DRAFT,
        ]);
    }

    #[Test]
    public function a_campaign_cannot_be_created_from_a_draft_questionnaire(): void
    {
        $questionnaire = Questionnaire::factory()->create([
            'tenant_id' => 'default',
            'status' => Questionnaire::STATUS_DRAFT,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/questionnaires/{$questionnaire->id}/campaigns")
            ->assertStatus(422);

        $this->assertDatabaseMissing('campaigns', ['questionnaire_id' => $questionnaire->id]);
    }

    #[Test]
    public function a_campaign_cannot_be_created_for_a_questionnaire_in_another_tenant(): void
    {
        $questionnaire = Questionnaire::factory()->create([
            'tenant_id' => 'other-tenant',
            'status' => Questionnaire::STATUS_READY,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/questionnaires/{$questionnaire->id}/campaigns")
            ->assertStatus(404);

        $this->assertDatabaseMissing('campaigns', ['questionnaire_id' => $questionnaire->id]);
    }

    #[Test]
    public function a_viewer_role_cannot_create_a_campaign(): void
    {
        $viewer = User::factory()->create(['tenant_id' => 'default']);
        $viewer->roles()->attach(Role::query()->where('code', Role::EXECUTIVE_VIEWER)->first());

        $questionnaire = Questionnaire::factory()->create([
            'tenant_id' => 'default',
            'status' => Questionnaire::STATUS_READY,
        ]);

        $this->actingAs($viewer)
            ->postJson("/api/questionnaires/{$questionnaire->id}/campaigns")
            ->assertStatus(403);
    }
}
