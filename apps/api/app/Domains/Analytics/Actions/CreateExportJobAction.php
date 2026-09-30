<?php

namespace App\Domains\Analytics\Actions;

use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use App\Domains\Analytics\Models\ExportJob;
use App\Domains\Campaigns\Models\Campaign;
use App\Jobs\GenerateAnalyticsExportJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// FSD §14.2 langkah 1-3 (Fase 5 slice 5). Dedup: request identik (user+
// campaign+filter+format) yang masih queued/processing mengembalikan job
// yang sudah ada alih-alih membuat baru -- tidak dedup terhadap job
// succeeded, karena user yang export ulang setelah data berubah harus
// dapat file baru (FSD §14.2 "idempotency/dedup ... selama jendela singkat").
class CreateExportJobAction
{
    public function execute(Campaign $campaign, User $requestedBy, AnalyticsFilterDTO $filter, string $format): ExportJob
    {
        $filterHash = self::filterHash($campaign, $filter, $format);

        $existing = ExportJob::query()
            ->where('requested_by', $requestedBy->id)
            ->where('campaign_id', $campaign->id)
            ->where('filter_hash', $filterHash)
            ->where('format', $format)
            ->whereIn('status', [ExportJob::STATUS_QUEUED, ExportJob::STATUS_PROCESSING])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $exportJob = DB::transaction(function () use ($campaign, $requestedBy, $filter, $format, $filterHash) {
            return ExportJob::query()->create([
                'tenant_id' => $campaign->tenant_id,
                'campaign_id' => $campaign->id,
                'requested_by' => $requestedBy->id,
                'format' => $format,
                'filter_json' => $filter->normalized(),
                'filter_hash' => $filterHash,
                'status' => ExportJob::STATUS_QUEUED,
                'requested_at' => now(),
                'expires_at' => now()->addHours(ExportJob::MAX_PROCESSING_WINDOW_HOURS),
            ]);
        });

        GenerateAnalyticsExportJob::dispatch($exportJob)->afterCommit();

        return $exportJob;
    }

    private static function filterHash(Campaign $campaign, AnalyticsFilterDTO $filter, string $format): string
    {
        return hash('sha256', json_encode([
            'campaign_id' => $campaign->id,
            'format' => $format,
            'filter' => $filter->normalized(),
        ]));
    }
}
