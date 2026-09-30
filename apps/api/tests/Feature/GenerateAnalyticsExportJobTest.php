<?php

namespace Tests\Feature;

use App\Domains\Analytics\Exceptions\ExportCampaignNotFoundException;
use App\Domains\Analytics\Models\ExportJob;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Respondents\Models\Answer;
use App\Domains\Respondents\Models\AnswerChoice;
use App\Domains\Respondents\Models\SurveyResponse;
use App\Jobs\GenerateAnalyticsExportJob;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

// FSD §14.2 langkah 4-6 (Fase 5 slice 5). Job dijalankan lewat pemanggilan
// handle() langsung (app()->call, resolusi dependency via container) --
// bukan lewat dispatch/queue -- supaya tidak terjebak semantik
// dispatch()->afterCommit() yang sengaja tidak pernah commit di bawah
// RefreshDatabase (transaksi test tidak pernah di-commit).
class GenerateAnalyticsExportJobTest extends TestCase
{
    use RefreshDatabase;

    private User $requestedBy;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->requestedBy = User::factory()->create(['tenant_id' => 'default']);

        $snapshot = QuestionnaireSnapshot::factory()->create([
            'structure_json' => [
                'sections' => [[
                    'code' => 'S1',
                    'title' => 'Bagian 1',
                    'questions' => [[
                        'code' => 'Q1', 'type' => 'single_choice', 'text' => 'Pilihan tunggal',
                        'options' => [
                            ['code' => 'OPT_A', 'label' => 'A', 'is_positive' => false],
                            ['code' => 'OPT_B', 'label' => 'B', 'is_positive' => false],
                        ],
                        'matrix_rows' => [],
                    ]],
                ]],
            ],
        ]);

        $this->campaign = Campaign::factory()->create([
            'tenant_id' => 'default',
            'snapshot_id' => $snapshot->id,
            'denominator_count' => 2,
            'minimum_segment_size' => 1,
        ]);
    }

    private function seedAnswers(int $optionACount, int $optionBCount): void
    {
        for ($i = 0; $i < $optionACount; $i++) {
            $response = SurveyResponse::factory()->create(['campaign_id' => $this->campaign->id, 'snapshot_id' => $this->campaign->snapshot_id]);
            $answer = Answer::factory()->create(['response_id' => $response->id, 'snapshot_question_code' => 'Q1', 'text_value' => null]);
            AnswerChoice::factory()->create(['answer_id' => $answer->id, 'option_code' => 'OPT_A']);
        }

        for ($i = 0; $i < $optionBCount; $i++) {
            $response = SurveyResponse::factory()->create(['campaign_id' => $this->campaign->id, 'snapshot_id' => $this->campaign->snapshot_id]);
            $answer = Answer::factory()->create(['response_id' => $response->id, 'snapshot_question_code' => 'Q1', 'text_value' => null]);
            AnswerChoice::factory()->create(['answer_id' => $answer->id, 'option_code' => 'OPT_B']);
        }
    }

    private function makeExportJob(): ExportJob
    {
        return ExportJob::factory()->create([
            'tenant_id' => 'default',
            'campaign_id' => $this->campaign->id,
            'requested_by' => $this->requestedBy->id,
            'filter_json' => [],
        ]);
    }

    private function loadSpreadsheet(ExportJob $exportJob): Spreadsheet
    {
        $localPath = Storage::disk('local')->path($exportJob->file_path);

        return IOFactory::load($localPath);
    }

    #[Test]
    public function successful_run_writes_file_and_marks_job_succeeded(): void
    {
        $this->seedAnswers(2, 1);
        $exportJob = $this->makeExportJob();

        $job = new GenerateAnalyticsExportJob($exportJob);
        app()->call([$job, 'handle']);

        $exportJob->refresh();

        $this->assertSame(ExportJob::STATUS_SUCCEEDED, $exportJob->status);
        $this->assertNotNull($exportJob->file_path);
        Storage::disk('local')->assertExists($exportJob->file_path);
        $this->assertGreaterThan(0, $exportJob->file_size);
        $this->assertNotNull($exportJob->checksum);
        $this->assertTrue($exportJob->expires_at->isFuture());

        $spreadsheet = $this->loadSpreadsheet($exportJob);
        $this->assertSame(
            ['Metadata', 'Response Summary', 'Question Results', 'Choice Distribution'],
            $spreadsheet->getSheetNames(),
        );
    }

    #[Test]
    public function suppressed_question_does_not_leak_option_rows_into_the_exported_file(): void
    {
        $this->campaign->update(['minimum_segment_size' => 5]);
        $this->seedAnswers(1, 1); // n=2 < threshold 5 -> disensor

        $exportJob = $this->makeExportJob();

        $job = new GenerateAnalyticsExportJob($exportJob);
        app()->call([$job, 'handle']);

        $exportJob->refresh();
        $this->assertSame(ExportJob::STATUS_SUCCEEDED, $exportJob->status);

        $spreadsheet = $this->loadSpreadsheet($exportJob);
        $distributionSheet = $spreadsheet->getSheetByName('Choice Distribution');
        $this->assertSame(1, $distributionSheet->getHighestDataRow(), 'Hanya baris header, tidak ada baris opsi untuk pertanyaan yang disensor.');

        $questionSheet = $spreadsheet->getSheetByName('Question Results');
        $this->assertStringContainsString('Disembunyikan', (string) $questionSheet->getCell('F2')->getValue());
    }

    #[Test]
    public function job_returns_without_error_when_export_job_row_is_gone(): void
    {
        $exportJob = $this->makeExportJob();
        $exportJob->delete();

        $job = new GenerateAnalyticsExportJob($exportJob);
        app()->call([$job, 'handle']);

        $this->assertTrue(true); // tidak melempar exception apa pun.
    }

    #[Test]
    public function tenant_mismatch_between_export_job_and_campaign_fails_permanently_without_being_retried(): void
    {
        $exportJob = $this->makeExportJob();
        // Simulasi data drift -- export_job.tenant_id tidak lagi cocok
        // dengan campaign.tenant_id (skenario yang seharusnya tidak pernah
        // terjadi lewat alur normal CreateExportJobAction, tapi job harus
        // tetap defensif memverifikasi ulang, bukan mempercayai payload).
        DB::table('export_jobs')->where('id', $exportJob->id)->update(['tenant_id' => 'other-tenant']);
        $exportJob->refresh();

        $job = new GenerateAnalyticsExportJob($exportJob);
        app()->call([$job, 'handle']);

        // $this->job (queue job wrapper) null saat handle() dipanggil
        // langsung -> $this->fail() no-op, status masih 'processing' di
        // titik ini. Simulasikan lifecycle queue worker yang sesungguhnya
        // (job->fail() memicu failed()) dengan memanggil failed() manual.
        $exportJob->refresh();
        $this->assertSame(ExportJob::STATUS_PROCESSING, $exportJob->status);

        $job->failed(new ExportCampaignNotFoundException);

        $exportJob->refresh();
        $this->assertSame(ExportJob::STATUS_FAILED, $exportJob->status);
        $this->assertSame('campaign_not_found', $exportJob->error_code);
    }

    #[Test]
    public function transient_failure_is_rethrown_so_queue_retry_applies(): void
    {
        $this->seedAnswers(1, 1);
        $exportJob = $this->makeExportJob();

        $failingDisk = Mockery::mock(Filesystem::class);
        $failingDisk->shouldReceive('putFileAs')->andThrow(new RuntimeException('disk penuh (simulasi)'));
        Storage::shouldReceive('disk')->with('local')->andReturn($failingDisk);

        $job = new GenerateAnalyticsExportJob($exportJob);

        $this->expectException(RuntimeException::class);

        try {
            app()->call([$job, 'handle']);
        } finally {
            $exportJob->refresh();
            $this->assertSame(ExportJob::STATUS_PROCESSING, $exportJob->status, 'status final belum ditulis sebelum retry habis/failed() dipanggil');
            $this->assertSame('RuntimeException', $exportJob->error_code);
        }
    }
}
