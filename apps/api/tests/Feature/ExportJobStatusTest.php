<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\ExportJob;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Identity\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §17.6 (Fase 5 slice 5).
class ExportJobStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['tenant_id' => 'default']);
        $this->admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $this->campaign = Campaign::factory()->create(['tenant_id' => 'default']);
    }

    #[Test]
    public function queued_job_status_has_no_download_url(): void
    {
        $job = ExportJob::factory()->create([
            'tenant_id' => 'default',
            'campaign_id' => $this->campaign->id,
            'requested_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/exports/{$job->id}");

        $response->assertOk();
        $response->assertJsonPath('data.status', ExportJob::STATUS_QUEUED);
        $response->assertJsonPath('data.download_url', null);
    }

    #[Test]
    public function succeeded_unexpired_job_exposes_a_signed_download_url(): void
    {
        $job = ExportJob::factory()->succeeded()->create([
            'tenant_id' => 'default',
            'campaign_id' => $this->campaign->id,
            'requested_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/exports/{$job->id}");

        $response->assertOk();
        $this->assertNotNull($response->json('data.download_url'));
        $this->assertStringContainsString("/api/exports/{$job->id}/download", $response->json('data.download_url'));
    }

    #[Test]
    public function succeeded_but_expired_job_has_no_download_url(): void
    {
        $job = ExportJob::factory()->succeeded()->create([
            'tenant_id' => 'default',
            'campaign_id' => $this->campaign->id,
            'requested_by' => $this->admin->id,
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/exports/{$job->id}");

        $response->assertOk();
        $response->assertJsonPath('data.download_url', null);
    }

    #[Test]
    public function cross_tenant_export_job_returns_403(): void
    {
        $otherCampaign = Campaign::factory()->create(['tenant_id' => 'other-tenant']);
        $otherAdmin = User::factory()->create(['tenant_id' => 'other-tenant']);
        $otherAdmin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $job = ExportJob::factory()->create([
            'tenant_id' => 'other-tenant',
            'campaign_id' => $otherCampaign->id,
            'requested_by' => $otherAdmin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/exports/{$job->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function unknown_export_job_returns_404(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/exports/'.(string) Str::uuid());

        $response->assertStatus(404);
    }
}
