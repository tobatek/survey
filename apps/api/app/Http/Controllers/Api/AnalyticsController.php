<?php

namespace App\Http\Controllers\Api;

use App\Domains\Analytics\Actions\CampaignAnalyticsSummaryAction;
use App\Domains\Analytics\Actions\CampaignQuestionResultsAction;
use App\Domains\Analytics\Actions\ListAnalyzableCampaignsAction;
use App\Domains\Campaigns\Models\Campaign;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

// FSD §13, §17.6 -- Fase 5 slice 2: endpoint hasil dasar, query langsung ke
// data mentah (belum materialized, itu slice 4) dan tanpa filter/threshold
// per-pertanyaan (itu slice 3) -- angka di sini bersifat campaign-wide.
class AnalyticsController extends Controller
{
    public function index(ListAnalyzableCampaignsAction $action): JsonResponse
    {
        Gate::authorize('viewAny', Campaign::class);

        return response()->json(['data' => $action->execute(Auth::user()->tenant_id)]);
    }

    public function summary(Campaign $campaign, CampaignAnalyticsSummaryAction $action): JsonResponse
    {
        Gate::authorize('view', $campaign);

        return response()->json(['data' => $action->execute($campaign)]);
    }

    public function questions(Campaign $campaign, CampaignQuestionResultsAction $action): JsonResponse
    {
        Gate::authorize('view', $campaign);

        return response()->json(['data' => $action->execute($campaign)]);
    }
}
