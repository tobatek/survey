<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\ExportJob;
use App\Domains\Campaigns\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §14.2 langkah 8 (Fase 5 slice 5).
class PruneExpiredExportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    #[Test]
    public function expired_succeeded_job_and_its_file_are_deleted(): void
    {
        $campaign = Campaign::factory()->create();
        $job = ExportJob::factory()->succeeded()->create([
            'campaign_id' => $campaign->id,
            'expires_at' => now()->subHour(),
        ]);
        Storage::disk('local')->put($job->file_path, 'isi');

        $this->artisan('exports:prune-expired')->assertSuccessful();

        $this->assertDatabaseMissing('export_jobs', ['id' => $job->id]);
        Storage::disk('local')->assertMissing($job->file_path);
    }

    #[Test]
    public function job_not_yet_expired_is_untouched(): void
    {
        $campaign = Campaign::factory()->create();
        $job = ExportJob::factory()->succeeded()->create([
            'campaign_id' => $campaign->id,
            'expires_at' => now()->addHour(),
        ]);
        Storage::disk('local')->put($job->file_path, 'isi');

        $this->artisan('exports:prune-expired')->assertSuccessful();

        $this->assertDatabaseHas('export_jobs', ['id' => $job->id]);
        Storage::disk('local')->assertExists($job->file_path);
    }

    #[Test]
    public function stuck_queued_job_past_fallback_window_is_deleted(): void
    {
        $campaign = Campaign::factory()->create();
        $job = ExportJob::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ExportJob::STATUS_QUEUED,
            'expires_at' => now()->subMinute(), // fallback expiry sudah lewat, tidak pernah sukses
        ]);

        $this->artisan('exports:prune-expired')->assertSuccessful();

        $this->assertDatabaseMissing('export_jobs', ['id' => $job->id]);
    }
}
