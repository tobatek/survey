<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\ExportJob;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Identity\Models\Role;
use App\Jobs\GenerateAnalyticsExportJob;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §14.2 langkah 1-3, §17.6 (Fase 5 slice 5).
class ExportJobCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $respondent;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['tenant_id' => 'default']);
        $this->admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $this->respondent = User::factory()->create(['tenant_id' => 'default']);

        $this->campaign = Campaign::factory()->create(['tenant_id' => 'default']);
    }

    #[Test]
    public function authorized_user_can_create_export_job_and_it_is_dispatched(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin)->postJson('/api/exports', [
            'campaign_id' => $this->campaign->id,
            'format' => 'xlsx',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', ExportJob::STATUS_QUEUED);

        $this->assertDatabaseHas('export_jobs', [
            'campaign_id' => $this->campaign->id,
            'requested_by' => $this->admin->id,
            'status' => ExportJob::STATUS_QUEUED,
        ]);

        Queue::assertPushedOn('exports', GenerateAnalyticsExportJob::class);
    }

    #[Test]
    public function respondent_role_cannot_create_export_job(): void
    {
        $response = $this->actingAs($this->respondent)->postJson('/api/exports', [
            'campaign_id' => $this->campaign->id,
            'format' => 'xlsx',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function cross_tenant_campaign_returns_403(): void
    {
        $otherCampaign = Campaign::factory()->create(['tenant_id' => 'other-tenant']);

        $response = $this->actingAs($this->admin)->postJson('/api/exports', [
            'campaign_id' => $otherCampaign->id,
            'format' => 'xlsx',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function format_other_than_xlsx_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/exports', [
            'campaign_id' => $this->campaign->id,
            'format' => 'csv',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function include_comments_true_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/exports', [
            'campaign_id' => $this->campaign->id,
            'format' => 'xlsx',
            'include_comments' => true,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function duplicate_request_while_first_is_still_queued_reuses_the_same_job(): void
    {
        Queue::fake();

        $first = $this->actingAs($this->admin)->postJson('/api/exports', [
            'campaign_id' => $this->campaign->id,
            'format' => 'xlsx',
        ]);

        $second = $this->actingAs($this->admin)->postJson('/api/exports', [
            'campaign_id' => $this->campaign->id,
            'format' => 'xlsx',
        ]);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('export_jobs', 1);
        Queue::assertPushed(GenerateAnalyticsExportJob::class, 1);
    }
}
