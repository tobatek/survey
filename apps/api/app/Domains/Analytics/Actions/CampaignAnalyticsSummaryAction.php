<?php

namespace App\Domains\Analytics\Actions;

use App\Domains\Analytics\Support\AnalyticsFormulas;
use App\Domains\Campaigns\Actions\CampaignMonitoringAction;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Respondents\Models\ResponseSession;
use Carbon\Carbon;

// FSD §13.2 bagian funnel/ringkasan (Fase 5 slice 2). Funnel + breakdown per
// kelompok (dengan threshold suppression) di-reuse langsung dari
// CampaignMonitoringAction (Fase 3) -- tidak ditulis ulang.
class CampaignAnalyticsSummaryAction
{
    public function __construct(private readonly CampaignMonitoringAction $monitoring) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Campaign $campaign): array
    {
        $monitoring = $this->monitoring->execute($campaign);

        $startedSessions = ResponseSession::query()->where('campaign_id', $campaign->id)->count();
        $completedSessions = ResponseSession::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', ResponseSession::STATUS_COMPLETED)
            ->count();

        $lastResponseAt = $campaign->responses()->max('submitted_at');

        return [
            'funnel' => $monitoring['funnel'],
            'breakdown' => $monitoring['breakdown'],
            'response_rate' => AnalyticsFormulas::responseRate(
                $monitoring['funnel'][Eligibility::STATUS_COMPLETED],
                $campaign->denominator_count ?? 0,
            ),
            'completion_rate' => AnalyticsFormulas::completionRate($completedSessions, $startedSessions),
            'total_responses' => $campaign->responses()->count(),
            'last_response_at' => $lastResponseAt ? Carbon::parse($lastResponseAt)->toIso8601String() : null,
        ];
    }
}
