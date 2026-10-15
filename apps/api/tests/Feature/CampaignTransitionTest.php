<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Actions\TransitionCampaignStatusAction;
use App\Domains\Campaigns\Exceptions\CampaignTransitionConflictException;
use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Identity\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignTransitionTest extends TestCase
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
    public function an_active_campaign_can_be_paused_then_resumed(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'status' => Campaign::STATUS_ACTIVE]);

        $pauseResponse = $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/pause");
        $pauseResponse->assertOk();
        $pauseResponse->assertJsonPath('data.status', Campaign::STATUS_PAUSED);
        $this->assertNotNull($campaign->fresh()->paused_at);

        $resumeResponse = $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/resume");
        $resumeResponse->assertOk();
        $resumeResponse->assertJsonPath('data.status', Campaign::STATUS_ACTIVE);
    }

    #[Test]
    public function an_active_campaign_can_be_closed(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'status' => Campaign::STATUS_ACTIVE]);

        $response = $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/close");

        $response->assertOk();
        $response->assertJsonPath('data.status', Campaign::STATUS_CLOSED);
        $this->assertNotNull($campaign->fresh()->closed_at);
    }

    #[Test]
    public function a_draft_campaign_cannot_be_paused(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'status' => Campaign::STATUS_DRAFT]);

        $response = $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/pause");

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'invalid_transition');
    }

    #[Test]
    public function a_closed_campaign_cannot_be_resumed(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'status' => Campaign::STATUS_CLOSED]);

        $response = $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/resume");

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'invalid_transition');
    }

    #[Test]
    public function stale_in_memory_status_triggers_transition_conflict(): void
    {
        // Race sebenarnya terjadi di level Action: model in-memory yang
        // dipakai punya status lama, tapi DB sudah berubah oleh request
        // lain di antara baca dan tulis. HTTP test tidak bisa mensimulasikan
        // ini (route model binding selalu baca fresh), jadi diuji langsung
        // terhadap Action.
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'status' => Campaign::STATUS_ACTIVE]);
        $staleCampaign = Campaign::query()->find($campaign->id);

        Campaign::query()->where('id', $campaign->id)->update(['status' => Campaign::STATUS_CLOSED]);

        $this->expectException(CampaignTransitionConflictException::class);

        app(TransitionCampaignStatusAction::class)->execute($staleCampaign, Campaign::STATUS_PAUSED);
    }

    #[Test]
    public function closing_a_campaign_expires_non_terminal_eligibilities_only(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'status' => Campaign::STATUS_ACTIVE]);

        $started = Eligibility::factory()->create([
            'campaign_id' => $campaign->id,
            'audience_member_id' => AudienceMember::factory()->create(['campaign_id' => $campaign->id])->id,
            'status' => Eligibility::STATUS_STARTED,
        ]);
        $completed = Eligibility::factory()->create([
            'campaign_id' => $campaign->id,
            'audience_member_id' => AudienceMember::factory()->create(['campaign_id' => $campaign->id])->id,
            'status' => Eligibility::STATUS_COMPLETED,
        ]);
        $excluded = Eligibility::factory()->create([
            'campaign_id' => $campaign->id,
            'audience_member_id' => AudienceMember::factory()->create(['campaign_id' => $campaign->id])->id,
            'status' => Eligibility::STATUS_EXCLUDED,
        ]);

        $this->actingAs($this->admin)->postJson("/api/campaigns/{$campaign->id}/close")->assertOk();

        $this->assertSame(Eligibility::STATUS_EXPIRED, $started->fresh()->status);
        $this->assertNotNull($started->fresh()->expired_at);
        $this->assertSame(Eligibility::STATUS_COMPLETED, $completed->fresh()->status);
        $this->assertSame(Eligibility::STATUS_EXCLUDED, $excluded->fresh()->status);
    }

    #[Test]
    public function transition_on_another_tenants_campaign_returns_403(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'other-tenant', 'status' => Campaign::STATUS_ACTIVE]);

        $this->actingAs($this->admin)
            ->postJson("/api/campaigns/{$campaign->id}/pause")
            ->assertStatus(403);
    }
}
