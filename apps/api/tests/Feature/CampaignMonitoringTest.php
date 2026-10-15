<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Identity\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['tenant_id' => 'default']);
        $this->admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $this->viewer = User::factory()->create(['tenant_id' => 'default']);
        $this->viewer->roles()->attach(Role::query()->where('code', Role::EXECUTIVE_VIEWER)->first());
    }

    private function makeMember(Campaign $campaign, string $programStudyCode, string $status): void
    {
        $member = AudienceMember::factory()->create([
            'campaign_id' => $campaign->id,
            'subject_type' => AudienceMember::SUBJECT_STUDENT,
            'attributes_snapshot_json' => ['program_study_code' => $programStudyCode],
        ]);

        Eligibility::factory()->create([
            'campaign_id' => $campaign->id,
            'audience_member_id' => $member->id,
            'status' => $status,
        ]);
    }

    #[Test]
    public function funnel_includes_all_statuses_with_zero_default(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'denominator_count' => 2]);

        $this->makeMember($campaign, 'TI', Eligibility::STATUS_COMPLETED);
        $this->makeMember($campaign, 'TI', Eligibility::STATUS_INVITED);

        $response = $this->actingAs($this->admin)->getJson("/api/campaigns/{$campaign->id}/monitoring");

        $response->assertOk();
        $response->assertJsonPath('data.funnel.completed', 1);
        $response->assertJsonPath('data.funnel.invited', 1);
        $response->assertJsonPath('data.funnel.eligible', 0);
        $response->assertJsonPath('data.funnel.opened', 0);
        $response->assertJsonPath('data.funnel.started', 0);
        $response->assertJsonPath('data.funnel.expired', 0);
        $response->assertJsonPath('data.funnel.excluded', 0);
    }

    #[Test]
    public function response_rate_is_computed_from_completed_over_denominator(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'denominator_count' => 4]);

        $this->makeMember($campaign, 'TI', Eligibility::STATUS_COMPLETED);

        $response = $this->actingAs($this->admin)->getJson("/api/campaigns/{$campaign->id}/monitoring");

        $response->assertOk();
        $response->assertJsonPath('data.response_rate', 0.25);
    }

    #[Test]
    public function response_rate_is_zero_when_denominator_is_empty(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'denominator_count' => null]);

        $response = $this->actingAs($this->admin)->getJson("/api/campaigns/{$campaign->id}/monitoring");

        $response->assertOk();
        $response->assertJsonPath('data.response_rate', 0);
    }

    #[Test]
    public function breakdown_groups_below_threshold_are_merged_into_lainnya(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default', 'minimum_segment_size' => 5]);

        foreach (range(1, 6) as $i) {
            $this->makeMember($campaign, 'TI', Eligibility::STATUS_COMPLETED);
        }
        $this->makeMember($campaign, 'SI', Eligibility::STATUS_INVITED);
        $this->makeMember($campaign, 'TE', Eligibility::STATUS_INVITED);

        $response = $this->actingAs($this->admin)->getJson("/api/campaigns/{$campaign->id}/monitoring");

        $response->assertOk();
        $breakdown = collect($response->json('data.breakdown.students'))->keyBy('group');

        $this->assertSame(6, $breakdown['TI']['total']);
        $this->assertSame(6, $breakdown['TI']['completed']);
        $this->assertSame(2, $breakdown['lainnya']['total']);
        $this->assertSame(0, $breakdown['lainnya']['completed']);
        $this->assertArrayNotHasKey('SI', $breakdown);
        $this->assertArrayNotHasKey('TE', $breakdown);
    }

    #[Test]
    public function executive_viewer_can_access_monitoring(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'default']);

        $this->actingAs($this->viewer)
            ->getJson("/api/campaigns/{$campaign->id}/monitoring")
            ->assertOk();
    }

    #[Test]
    public function monitoring_on_another_tenants_campaign_returns_403(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => 'other-tenant']);

        $this->actingAs($this->admin)
            ->getJson("/api/campaigns/{$campaign->id}/monitoring")
            ->assertStatus(403);
    }
}
