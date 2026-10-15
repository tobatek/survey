<?php

namespace App\Domains\Analytics\Actions;

use App\Domains\Analytics\Support\AnalyticsFormulas;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use Carbon\Carbon;

// FSD §13.1 (Fase 5 slice 2) — daftar campaign aktif/ditutup yang punya
// respons, untuk halaman awal "Hasil Analisa Kuesioner".
class ListAnalyzableCampaignsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(string $tenantId): array
    {
        $campaigns = Campaign::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [Campaign::STATUS_ACTIVE, Campaign::STATUS_CLOSED])
            ->whereHas('responses')
            ->with('questionnaire')
            ->withCount(['eligibilities as completed_count' => fn ($query) => $query->where('status', Eligibility::STATUS_COMPLETED)])
            ->get();

        return $campaigns->map(function (Campaign $campaign) {
            $lastResponseAt = $campaign->responses()->max('submitted_at');

            return [
                'id' => $campaign->id,
                'questionnaire_code' => $campaign->questionnaire->code,
                'questionnaire_title' => $campaign->questionnaire->title,
                'status' => $campaign->status,
                'starts_at' => $campaign->starts_at?->toIso8601String(),
                'ends_at' => $campaign->ends_at?->toIso8601String(),
                'denominator_count' => $campaign->denominator_count,
                'completed_count' => $campaign->completed_count,
                'response_rate' => AnalyticsFormulas::responseRate($campaign->completed_count, $campaign->denominator_count ?? 0),
                'last_response_at' => $lastResponseAt ? Carbon::parse($lastResponseAt)->toIso8601String() : null,
                // Slice 4 (materialized/aggregate table) belum membangun job
                // refresh `analysis_aggregates` -- field ini selalu null
                // sampai strategi agregasi lanjutan itu ada.
                'last_aggregated_at' => null,
            ];
        })->all();
    }
}
