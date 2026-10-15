<?php

namespace App\Domains\Analytics\Actions;

use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use App\Domains\Analytics\Support\AnalyticsFormulas;
use App\Domains\Analytics\Support\SegmentFilterScope;
use App\Domains\Analytics\Support\SegmentSizeGate;
use App\Domains\Campaigns\Actions\CampaignMonitoringAction;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Respondents\Models\ResponseSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// FSD §13.2 bagian funnel/ringkasan (Fase 5 slice 2-3).
//
// Tanpa filter: funnel + breakdown per kelompok (dengan threshold
// suppression bawaannya) di-reuse langsung dari CampaignMonitoringAction
// (Fase 3) -- angka campaign-wide dianggap aman tanpa suppression tambahan.
//
// DENGAN filter (slice 3): funnel dihitung ULANG lewat query baru yang
// menerapkan SegmentFilterScope ke eligibilities+audience_members --
// SENGAJA tidak lewat CampaignMonitoringAction (signature-nya tidak diubah
// supaya dashboard monitoring Fase 3 yang sudah ada tidak kena dampak).
// Hasil yang sudah difilter lalu digerbang SegmentSizeGate: kalau completed
// eligibility di bawah minimum_segment_size, SELURUH summary disembunyikan
// (beda dari endpoint .../questions yang menyembunyikan per pertanyaan --
// keputusan produk yang sudah dikonfirmasi user, karena funnel cuma satu
// angka utuh, bukan banyak pertanyaan independen).
//
// `completion_rate` SENGAJA tetap dihitung campaign-wide meski filter
// aktif -- itu metrik kualitas proses pengisian (draft vs selesai), FSD
// §13.2 hanya minta funnel & hasil pertanyaan yang difilter per segmen.
class CampaignAnalyticsSummaryAction
{
    private const FUNNEL_STATUSES = [
        Eligibility::STATUS_ELIGIBLE,
        Eligibility::STATUS_INVITED,
        Eligibility::STATUS_OPENED,
        Eligibility::STATUS_STARTED,
        Eligibility::STATUS_COMPLETED,
        Eligibility::STATUS_EXPIRED,
        Eligibility::STATUS_EXCLUDED,
    ];

    public function __construct(private readonly CampaignMonitoringAction $monitoring) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Campaign $campaign, AnalyticsFilterDTO $filter): array
    {
        $completionRate = $this->completionRate($campaign);

        if ($filter->isEmpty()) {
            $monitoring = $this->monitoring->execute($campaign);
            $responseStats = $this->responseStats($campaign, $filter);

            return [
                'funnel' => $monitoring['funnel'],
                'breakdown' => $monitoring['breakdown'],
                'response_rate' => AnalyticsFormulas::responseRate(
                    $monitoring['funnel'][Eligibility::STATUS_COMPLETED],
                    $campaign->denominator_count ?? 0,
                ),
                'completion_rate' => $completionRate,
                'total_responses' => $responseStats['total'],
                'last_response_at' => $responseStats['last_response_at'],
            ];
        }

        $funnel = $this->filteredFunnel($campaign, $filter);
        $responseStats = $this->responseStats($campaign, $filter);

        if (SegmentSizeGate::isSuppressed($funnel[Eligibility::STATUS_COMPLETED], $campaign->minimum_segment_size)) {
            return [
                'suppressed' => true,
                'minimum_segment_size' => $campaign->minimum_segment_size,
            ];
        }

        return [
            'funnel' => $funnel,
            'response_rate' => AnalyticsFormulas::responseRate($funnel[Eligibility::STATUS_COMPLETED], $campaign->denominator_count ?? 0),
            'completion_rate' => $completionRate,
            'total_responses' => $responseStats['total'],
            'last_response_at' => $responseStats['last_response_at'],
        ];
    }

    /**
     * @return array{numerator: int, denominator: int, value: float|null}
     */
    private function completionRate(Campaign $campaign): array
    {
        $startedSessions = ResponseSession::query()->where('campaign_id', $campaign->id)->count();
        $completedSessions = ResponseSession::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', ResponseSession::STATUS_COMPLETED)
            ->count();

        return AnalyticsFormulas::completionRate($completedSessions, $startedSessions);
    }

    /**
     * @return array<string, int>
     */
    private function filteredFunnel(Campaign $campaign, AnalyticsFilterDTO $filter): array
    {
        $query = DB::table('eligibilities')
            ->join('audience_members', 'audience_members.id', '=', 'eligibilities.audience_member_id')
            ->where('eligibilities.campaign_id', $campaign->id);

        SegmentFilterScope::applyToAudienceMemberScope($query, $filter);

        $counts = $query
            ->selectRaw('eligibilities.status, count(*) as total')
            ->groupBy('eligibilities.status')
            ->pluck('total', 'status');

        $funnel = [];
        foreach (self::FUNNEL_STATUSES as $status) {
            $funnel[$status] = (int) ($counts[$status] ?? 0);
        }

        return $funnel;
    }

    /**
     * @return array{total: int, last_response_at: ?string}
     */
    private function responseStats(Campaign $campaign, AnalyticsFilterDTO $filter): array
    {
        $query = DB::table('responses')->where('responses.campaign_id', $campaign->id);
        SegmentFilterScope::applyToResponseScope($query, $filter);

        $total = (clone $query)->count();
        $lastResponseAt = (clone $query)->max('submitted_at');

        return [
            'total' => $total,
            'last_response_at' => $lastResponseAt ? Carbon::parse($lastResponseAt)->toIso8601String() : null,
        ];
    }
}
