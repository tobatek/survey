<?php

namespace App\Domains\Campaigns\Actions;

use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use Illuminate\Support\Facades\DB;

// FSD §10 — agregasi dashboard monitoring: funnel status eligibility +
// breakdown per kelompok (prodi/unit) dengan threshold privasi. Query
// breakdown pakai sintaks agregat Postgres (`FILTER (WHERE ...)`, operator
// JSON `->>`) — proyek ini Postgres-only (config/database.php default
// pgsql di .env.example), jadi tidak dibuat portable ke driver lain.
class CampaignMonitoringAction
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

    private const BREAKDOWN_COLUMN = [
        AudienceMember::SUBJECT_STUDENT => 'program_study_code',
        AudienceMember::SUBJECT_EMPLOYEE => 'work_unit_name',
    ];

    public function execute(Campaign $campaign): array
    {
        $funnel = $this->funnel($campaign);
        $completed = $funnel[Eligibility::STATUS_COMPLETED];
        $denominator = $campaign->denominator_count ?? 0;

        return [
            'denominator_count' => $denominator,
            'funnel' => $funnel,
            'response_rate' => $denominator > 0 ? $completed / $denominator : 0.0,
            'breakdown' => [
                'students' => $this->breakdown($campaign, AudienceMember::SUBJECT_STUDENT),
                'employees' => $this->breakdown($campaign, AudienceMember::SUBJECT_EMPLOYEE),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function funnel(Campaign $campaign): array
    {
        $counts = Eligibility::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $funnel = [];

        foreach (self::FUNNEL_STATUSES as $status) {
            $funnel[$status] = (int) ($counts[$status] ?? 0);
        }

        return $funnel;
    }

    /**
     * @return list<array{group: string, total: int, completed: int}>
     */
    private function breakdown(Campaign $campaign, string $subjectType): array
    {
        $column = self::BREAKDOWN_COLUMN[$subjectType];

        $rows = DB::table('audience_members')
            ->leftJoin('eligibilities', 'eligibilities.audience_member_id', '=', 'audience_members.id')
            ->where('audience_members.campaign_id', $campaign->id)
            ->where('audience_members.subject_type', $subjectType)
            ->selectRaw("audience_members.attributes_snapshot_json->>'{$column}' as group_key")
            ->selectRaw('count(distinct audience_members.id) as total')
            ->selectRaw("count(*) filter (where eligibilities.status = 'completed') as completed")
            ->groupBy('group_key')
            ->get();

        $visible = [];
        $hidden = ['total' => 0, 'completed' => 0];

        foreach ($rows as $row) {
            $group = $row->group_key ?? 'tidak diketahui';
            $total = (int) $row->total;
            $completed = (int) $row->completed;

            if ($total < $campaign->minimum_segment_size) {
                $hidden['total'] += $total;
                $hidden['completed'] += $completed;

                continue;
            }

            $visible[] = ['group' => $group, 'total' => $total, 'completed' => $completed];
        }

        if ($hidden['total'] > 0) {
            $visible[] = ['group' => 'lainnya', 'total' => $hidden['total'], 'completed' => $hidden['completed']];
        }

        return $visible;
    }
}
