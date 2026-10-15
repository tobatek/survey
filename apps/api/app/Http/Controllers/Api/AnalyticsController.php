<?php

namespace App\Http\Controllers\Api;

use App\Domains\Analytics\Actions\CampaignAnalyticsSummaryAction;
use App\Domains\Analytics\Actions\CampaignCommentsAction;
use App\Domains\Analytics\Actions\CampaignQuestionResultsAction;
use App\Domains\Analytics\Actions\ListAnalyzableCampaignsAction;
use App\Domains\Analytics\Exceptions\CommentQuestionNotFoundException;
use App\Domains\Audit\Actions\RecordAuditEventAction;
use App\Domains\Campaigns\Models\Campaign;
use App\Http\Controllers\Api\Concerns\ResolvesAnalyticsFilter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

// FSD §13, §17.6 -- Fase 5 slice 2-3: endpoint hasil dasar + filter segmen
// (FSD §13.2), query langsung ke data mentah (belum materialized, itu
// slice 4). Filter SELALU berbasis snapshot attributes, bukan data master
// live -- lihat SegmentFilterScope.
class AnalyticsController extends Controller
{
    use ResolvesAnalyticsFilter;

    public function index(ListAnalyzableCampaignsAction $action): JsonResponse
    {
        Gate::authorize('viewAny', Campaign::class);

        return response()->json(['data' => $action->execute(Auth::user()->tenant_id)]);
    }

    public function summary(Request $request, Campaign $campaign, CampaignAnalyticsSummaryAction $action): JsonResponse
    {
        Gate::authorize('view', $campaign);

        return response()->json(['data' => $action->execute($campaign, $this->filterFromRequest($request))]);
    }

    public function questions(Request $request, Campaign $campaign, CampaignQuestionResultsAction $action): JsonResponse
    {
        Gate::authorize('view', $campaign);

        return response()->json(['data' => $action->execute($campaign, $this->filterFromRequest($request))]);
    }

    // FSD §17.6/§12.2/§21 -- Fase 5 slice 6 bagian 1. Otorisasi TERPISAH
    // (viewComments, bukan view) -- lihat CampaignPolicy. Audit log wajib
    // per akses, TIDAK PERNAH mengirim isi teks komentar sebagai context.
    public function comments(Request $request, Campaign $campaign, CampaignCommentsAction $action, RecordAuditEventAction $audit): JsonResponse
    {
        Gate::authorize('viewComments', $campaign);

        $data = $request->validate([
            'question_code' => ['required', 'string'],
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $result = $action->execute(
                $campaign,
                $data['question_code'],
                $this->filterFromRequest($request),
                $data['search'] ?? null,
                $data['per_page'] ?? 15,
            );
        } catch (CommentQuestionNotFoundException $e) {
            abort(422, $e->getMessage());
        }

        $audit->execute('analytics.comments.viewed', 'success', Auth::user(), 'campaign', $campaign->id, [
            'question_code' => $data['question_code'],
            'suppressed' => $result['suppressed'] ?? false,
        ]);

        return response()->json(['data' => $result]);
    }
}
