<?php

namespace App\Domains\Respondents\Actions;

use App\Domains\Respondents\Models\ResponseSession;
use App\Domains\Respondents\Support\AssertResponseSessionUsable;
use App\Domains\Respondents\Support\DraftReachabilityCalculator;

// FSD §11.2/§17.2 -- GET /api/responses/{session}: muat draft + struktur
// snapshot supaya frontend bisa membangun form (dipakai jalur identified
// maupun anonymous, lihat Http\Controllers\Api\Concerns\HandlesDraftLock
// untuk pembagian otorisasinya).
class LoadDraftAction
{
    public function __construct(
        private readonly AssertResponseSessionUsable $assertUsable,
        private readonly DraftReachabilityCalculator $reachability,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(ResponseSession $session): array
    {
        $this->assertUsable->execute($session);

        $session->loadMissing('snapshot');
        $structure = $session->snapshot->structure_json;
        $draftAnswers = $session->draft_answers_json ?? [];

        return [
            'session_id' => $session->id,
            'status' => $session->status,
            'mode' => $session->mode,
            'lock_version' => $session->lock_version,
            'expires_at' => $session->expires_at?->toIso8601String(),
            'structure' => $structure,
            'draft_answers' => $draftAnswers,
            'reachable_section_ids' => $this->reachability->reachableSectionIds($structure, $draftAnswers),
        ];
    }
}
