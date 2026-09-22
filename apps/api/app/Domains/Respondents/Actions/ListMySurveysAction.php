<?php

namespace App\Domains\Respondents\Actions;

use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Respondents\Models\ResponseSession;
use App\Domains\Respondents\Support\ResolveAudienceSubject;
use App\Models\User;

// FSD §11.1 -- daftar "Survei Saya" utk user yang lagi login (jalur
// identified). Jalur anonymous (link token) tidak pernah lewat sini, tidak
// perlu login sama sekali.
class ListMySurveysAction
{
    public function __construct(private readonly ResolveAudienceSubject $resolveSubject) {}

    /**
     * @return list<array{eligibility_id: string, campaign_id: string, title: string, category: ?string, starts_at: ?string, ends_at: ?string, respondent_status: string}>
     */
    public function execute(User $user): array
    {
        $subject = $this->resolveSubject->forUser($user);

        if ($subject === null) {
            return [];
        }

        $eligibilities = Eligibility::query()
            ->whereHas('audienceMember', function ($query) use ($subject) {
                $query->where('subject_type', $subject['type'])->where('subject_id', $subject['id']);
            })
            ->with(['campaign.questionnaire'])
            ->get();

        return $eligibilities->map(fn (Eligibility $eligibility) => [
            'eligibility_id' => $eligibility->id,
            'campaign_id' => $eligibility->campaign->id,
            'title' => $eligibility->campaign->questionnaire->title,
            'category' => $eligibility->campaign->questionnaire->category,
            'starts_at' => $eligibility->campaign->starts_at?->toIso8601String(),
            'ends_at' => $eligibility->campaign->ends_at?->toIso8601String(),
            'respondent_status' => $this->respondentStatus($eligibility),
        ])->values()->all();
    }

    private function respondentStatus(Eligibility $eligibility): string
    {
        if ($eligibility->status === Eligibility::STATUS_COMPLETED) {
            return 'completed';
        }

        if ($eligibility->campaign->status === Campaign::STATUS_CLOSED) {
            return 'closed';
        }

        $hasDraft = ResponseSession::query()
            ->where('eligibility_id', $eligibility->id)
            ->where('status', ResponseSession::STATUS_DRAFT)
            ->exists();

        return $hasDraft ? 'draft' : 'not_started';
    }
}
