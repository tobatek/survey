<?php

namespace App\Domains\Campaigns\Actions;

use App\Domains\Campaigns\DTO\AudienceFilterDTO;
use App\Domains\Campaigns\Models\Campaign;

// FSD §10.1 — hitung preview count + breakdown target sebelum aktivasi.
// Pure read: tidak menulis apa pun ke DB (penyimpanan target_rule_json
// jadi tanggung jawab controller, terpisah dari perhitungan).
class PreviewAudienceAction
{
    public function __construct(private readonly BuildAudienceQueryAction $buildQuery) {}

    public function execute(Campaign $campaign, AudienceFilterDTO $filter): array
    {
        $studentQuery = $this->buildQuery->studentQuery($filter, $campaign->tenant_id);
        $employeeQuery = $this->buildQuery->employeeQuery($filter, $campaign->tenant_id);

        $studentCount = (clone $studentQuery)->count();
        $employeeCount = (clone $employeeQuery)->count();

        $studentBreakdown = $this->applyThreshold(
            (clone $studentQuery)
                ->selectRaw('program_study_code, count(*) as total')
                ->groupBy('program_study_code')
                ->pluck('total', 'program_study_code')
                ->toArray(),
            $campaign->minimum_segment_size,
        );

        $employeeBreakdown = $this->applyThreshold(
            (clone $employeeQuery)
                ->selectRaw('work_unit_name, count(*) as total')
                ->groupBy('work_unit_name')
                ->pluck('total', 'work_unit_name')
                ->toArray(),
            $campaign->minimum_segment_size,
        );

        return [
            'total' => $studentCount + $employeeCount,
            'is_empty' => ($studentCount + $employeeCount) === 0,
            'students' => ['count' => $studentCount, 'breakdown' => $studentBreakdown],
            'employees' => ['count' => $employeeCount, 'breakdown' => $employeeBreakdown],
        ];
    }

    /**
     * @param  array<string, int>  $breakdown
     * @return array<string, int>
     */
    private function applyThreshold(array $breakdown, int $minimumSegmentSize): array
    {
        $visible = [];
        $hiddenTotal = 0;

        foreach ($breakdown as $key => $total) {
            if ($total < $minimumSegmentSize) {
                $hiddenTotal += $total;

                continue;
            }

            $visible[(string) $key] = $total;
        }

        if ($hiddenTotal > 0) {
            $visible['lainnya'] = $hiddenTotal;
        }

        return $visible;
    }
}
