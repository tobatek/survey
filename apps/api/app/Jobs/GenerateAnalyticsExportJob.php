<?php

namespace App\Jobs;

use App\Domains\Analytics\Actions\CampaignAnalyticsSummaryAction;
use App\Domains\Analytics\Actions\CampaignQuestionResultsAction;
use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use App\Domains\Analytics\Exceptions\ExportCampaignNotFoundException;
use App\Domains\Analytics\Models\ExportJob;
use App\Domains\Analytics\Support\AnalyticsCsvExportBuilder;
use App\Domains\Analytics\Support\AnalyticsPdfExportBuilder;
use App\Domains\Analytics\Support\AnalyticsXlsxExportBuilder;
use App\Domains\Analytics\Support\FunnelBarChartRenderer;
use App\Domains\Campaigns\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

// FSD §14.2 langkah 4-6 (Fase 5 slice 5). Queue `exports` terpisah dari
// `notifications` supaya burst export tidak mengganggu pengiriman undangan.
//
// Beda dari SendCampaignInvitationsJob (yang menelan Throwable per-item
// tanpa rethrow, karena kegagalannya inheren parsial per penerima): export
// job ini satu artefak atomik, retry transient failure (disk penuh, koneksi
// DB, OOM) aman diulang -- tidak ada efek samping eksternal yang
// terduplikasi. $tries/$backoff karena itu DIAKTIFKAN di sini.
//
// Status `succeeded`/`failed` FINAL hanya ditulis dari happy-path handle()
// atau dari failed() (dipanggil sekali setelah semua attempt habis) --
// supaya polling client (GET /api/exports/{id}) tidak melihat status
// "flap" di tengah-tengah retry sebuah attempt yang gagal transient.
class GenerateAnalyticsExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(private readonly ExportJob $exportJob)
    {
        $this->onQueue('exports');
    }

    public function handle(
        CampaignAnalyticsSummaryAction $summaryAction,
        CampaignQuestionResultsAction $questionsAction,
        AnalyticsXlsxExportBuilder $xlsxBuilder,
        AnalyticsCsvExportBuilder $csvBuilder,
        AnalyticsPdfExportBuilder $pdfBuilder,
        FunnelBarChartRenderer $chartRenderer,
    ): void {
        $exportJob = $this->exportJob->fresh();

        if ($exportJob === null) {
            return;
        }

        // Conditional update sebagai lock -- pola sama transisi status
        // campaign (TransitionCampaignStatusAction): kalau affected 0 rows,
        // attempt lain sudah mengklaim job ini atau statusnya sudah
        // terminal, jadi tidak perlu diproses lagi.
        $claimed = ExportJob::query()
            ->where('id', $exportJob->id)
            ->where('status', ExportJob::STATUS_QUEUED)
            ->update(['status' => ExportJob::STATUS_PROCESSING, 'started_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $campaign = Campaign::query()->find($exportJob->campaign_id);

        if ($campaign === null || $campaign->tenant_id !== $exportJob->tenant_id) {
            $this->fail(new ExportCampaignNotFoundException);

            return;
        }

        try {
            $filter = AnalyticsFilterDTO::fromArray($exportJob->filter_json ?? []);

            // Data yang di-fetch disesuaikan per format -- hemat query yang
            // tidak dipakai (mis. format csv/png tidak perlu keduanya).
            $needsQuestions = in_array($exportJob->format, [ExportJob::FORMAT_XLSX, ExportJob::FORMAT_CSV, ExportJob::FORMAT_PDF], true);
            $needsSummary = in_array($exportJob->format, [ExportJob::FORMAT_XLSX, ExportJob::FORMAT_PDF, ExportJob::FORMAT_PNG], true);

            $questions = $needsQuestions ? $questionsAction->execute($campaign, $filter) : null;
            $summary = $needsSummary ? $summaryAction->execute($campaign, $filter) : null;
            $questionnaireTitle = $exportJob->format !== ExportJob::FORMAT_CSV
                ? ($campaign->questionnaire()->value('title') ?? 'Survei')
                : null;

            $directory = "exports/{$exportJob->tenant_id}/{$campaign->id}";
            $filename = Str::uuid().'.'.$exportJob->format;
            $tempPath = tempnam(sys_get_temp_dir(), 'export_');

            match ($exportJob->format) {
                ExportJob::FORMAT_XLSX => (new Xlsx(
                    $xlsxBuilder->build($campaign, $questionnaireTitle, $exportJob->requestedBy, $filter, $summary, $questions)
                ))->save($tempPath),
                ExportJob::FORMAT_CSV => file_put_contents($tempPath, $csvBuilder->build($questions)),
                ExportJob::FORMAT_PDF => file_put_contents($tempPath, $pdfBuilder->build(
                    $campaign, $questionnaireTitle, $exportJob->requestedBy, $filter, $summary, $questions
                )),
                ExportJob::FORMAT_PNG => file_put_contents($tempPath, $chartRenderer->render(
                    $campaign, $questionnaireTitle, $filter, $summary
                )),
            };

            Storage::disk('local')->putFileAs($directory, new File($tempPath), $filename);

            $storedPath = "{$directory}/{$filename}";
            $fileSize = filesize($tempPath);
            $checksum = hash_file('sha256', $tempPath);
            unlink($tempPath);

            $exportJob->update([
                'status' => ExportJob::STATUS_SUCCEEDED,
                'file_path' => $storedPath,
                'file_size' => $fileSize,
                'checksum' => $checksum,
                'completed_at' => now(),
                'expires_at' => now()->addHours(ExportJob::DOWNLOAD_TTL_HOURS),
            ]);
        } catch (Throwable $e) {
            $exportJob->update(['error_code' => class_basename($e)]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $errorCode = $exception instanceof ExportCampaignNotFoundException
            ? 'campaign_not_found'
            : ($exception !== null ? class_basename($exception) : 'unknown');

        $this->exportJob->fresh()?->update([
            'status' => ExportJob::STATUS_FAILED,
            'error_code' => $errorCode,
            'completed_at' => now(),
        ]);
    }
}
