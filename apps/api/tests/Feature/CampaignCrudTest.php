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

    #[Test]
    public function show_returns_campaign_detail(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default']);

        $response = $this->actingAs($this->admin)->getJson("/api/campaigns/{$campaign->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $campaign->id);
    }

    #[Test]
    public function show_on_another_tenants_campaign_returns_403(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'other-tenant']);

        $this->actingAs($this->admin)
            ->getJson("/api/campaigns/{$campaign->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function index_for_questionnaire_lists_its_campaigns(): void
    {
        $questionnaire = Questionnaire::factory()->create(['tenant_id' => 'default']);
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'questionnaire_id' => $questionnaire->id]);

        $response = $this->actingAs($this->admin)->getJson("/api/questionnaires/{$questionnaire->id}/campaigns");

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $campaign->id);
    }

    #[Test]
    public function index_for_questionnaire_returns_empty_when_none_exists(): void
    {
        $questionnaire = Questionnaire::factory()->create(['tenant_id' => 'default']);

        $response = $this->actingAs($this->admin)->getJson("/api/questionnaires/{$questionnaire->id}/campaigns");

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function schedule_can_be_set_while_draft(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default']);

        $response = $this->actingAs($this->admin)->patchJson("/api/campaigns/{$campaign->id}/schedule", [
            'starts_at' => '2026-10-01T00:00:00Z',
            'ends_at' => '2026-10-15T00:00:00Z',
            'timezone' => 'Asia/Jakarta',
        ]);

        $response->assertOk();
        $this->assertNotNull($campaign->fresh()->starts_at);
        $this->assertSame('Asia/Jakarta', $campaign->fresh()->timezone);
    }

    #[Test]
    public function schedule_rejects_end_before_start(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default']);

        $this->actingAs($this->admin)->patchJson("/api/campaigns/{$campaign->id}/schedule", [
            'starts_at' => '2026-10-15T00:00:00Z',
            'ends_at' => '2026-10-01T00:00:00Z',
        ])->assertStatus(422);
    }

    #[Test]
    public function schedule_cannot_be_changed_once_active(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'status' => Campaign::STATUS_ACTIVE]);

        $this->actingAs($this->admin)->patchJson("/api/campaigns/{$campaign->id}/schedule", [
            'starts_at' => '2026-10-01T00:00:00Z',
        ])->assertStatus(422);
    }
}
