<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\ExportJob;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Identity\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §14.2 langkah 7, §21.2 (Fase 5 slice 5) -- signed URL + otorisasi
// Gate berlapis (bukan salah satu saja).
class ExportDownloadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['tenant_id' => 'default']);
        $this->admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $this->campaign = Campaign::factory()->create(['tenant_id' => 'default']);
    }

    private function makeSucceededJobWithFile(array $overrides = []): ExportJob
    {
        $job = ExportJob::factory()->succeeded()->create(array_merge([
            'tenant_id' => 'default',
            'campaign_id' => $this->campaign->id,
            'requested_by' => $this->admin->id,
        ], $overrides));

        Storage::disk('local')->put($job->file_path, 'isi-file-xlsx-simulasi');

        return $job;
    }

    #[Test]
    public function valid_signed_url_downloads_the_file(): void
    {
        $job = $this->makeSucceededJobWithFile();

        $url = URL::temporarySignedRoute('exports.download', now()->addMinutes(15), ['export' => $job->id]);

        $response = $this->actingAs($this->admin)->get($url);

        $response->assertOk();
        $this->assertSame('isi-file-xlsx-simulasi', $response->streamedContent());
    }

    #[Test]
    public function tampered_signature_is_rejected(): void
    {
        $job = $this->makeSucceededJobWithFile();

        $url = URL::temporarySignedRoute('exports.download', now()->addMinutes(15), ['export' => $job->id]);
        $tampered = $url.'&tampered=1';

        $response = $this->actingAs($this->admin)->get($tampered);

        $response->assertStatus(403);
    }

    #[Test]
    public function expired_signature_is_rejected(): void
    {
        $job = $this->makeSucceededJobWithFile();

        $url = URL::temporarySignedRoute('exports.download', now()->subMinute(), ['export' => $job->id]);

        $response = $this->actingAs($this->admin)->get($url);

        $response->assertStatus(403);
    }

    #[Test]
    public function expires_at_passed_returns_410_even_with_valid_signature(): void
    {
        $job = $this->makeSucceededJobWithFile(['expires_at' => now()->subHour()]);

        $url = URL::temporarySignedRoute('exports.download', now()->addMinutes(15), ['export' => $job->id]);

        $response = $this->actingAs($this->admin)->get($url);

        $response->assertStatus(410);
    }

    #[Test]
    public function cross_tenant_user_is_rejected_even_with_valid_signature(): void
    {
        $job = $this->makeSucceededJobWithFile();

        $otherAdmin = User::factory()->create(['tenant_id' => 'other-tenant']);
        $otherAdmin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());

        $url = URL::temporarySignedRoute('exports.download', now()->addMinutes(15), ['export' => $job->id]);

        $response = $this->actingAs($otherAdmin)->get($url);

        $response->assertStatus(403);
    }

    #[Test]
    public function csv_export_downloads_with_csv_filename_extension(): void
    {
        $job = $this->makeSucceededJobWithFile([
            'format' => ExportJob::FORMAT_CSV,
            'file_path' => 'exports/default/'.str()->uuid().'.csv',
        ]);

        $url = URL::temporarySignedRoute('exports.download', now()->addMinutes(15), ['export' => $job->id]);
        $response = $this->actingAs($this->admin)->get($url);

        $response->assertOk();
        $this->assertStringContainsString('.csv', (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function job_not_yet_succeeded_returns_404(): void
    {
        $job = ExportJob::factory()->create([
            'tenant_id' => 'default',
            'campaign_id' => $this->campaign->id,
            'requested_by' => $this->admin->id,
        ]);

        $url = URL::temporarySignedRoute('exports.download', now()->addMinutes(15), ['export' => $job->id]);

        $response = $this->actingAs($this->admin)->get($url);

        $response->assertStatus(404);
    }
}
