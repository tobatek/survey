<?php

namespace App\Http\Controllers\Api;

use App\Domains\Analytics\Actions\CampaignAnalyticsSummaryAction;
use App\Domains\Analytics\Actions\CampaignQuestionResultsAction;
use App\Domains\Analytics\Actions\ListAnalyzableCampaignsAction;
use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
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

    private function filterFromRequest(Request $request): AnalyticsFilterDTO
    {
        $data = $request->validate([
            'respondent_types' => ['sometimes', 'array'],
            'respondent_types.*' => ['string', 'in:'.AudienceMember::SUBJECT_STUDENT.','.AudienceMember::SUBJECT_EMPLOYEE],
            'faculty_names' => ['sometimes', 'array'],
            'faculty_names.*' => ['string'],
            'program_study_codes' => ['sometimes', 'array'],
            'program_study_codes.*' => ['string'],
            'degree_levels' => ['sometimes', 'array'],
            'degree_levels.*' => ['string'],
            'cohort_years' => ['sometimes', 'array'],
            'cohort_years.*' => ['string'],
            'work_unit_names' => ['sometimes', 'array'],
            'work_unit_names.*' => ['string'],
            'employee_types' => ['sometimes', 'array'],
            'employee_types.*' => ['string'],
            'submitted_from' => ['sometimes', 'date'],
            'submitted_to' => ['sometimes', 'date', 'after_or_equal:submitted_from'],
            'question_search' => ['sometimes', 'string', 'max:255'],
        ]);

        return AnalyticsFilterDTO::fromArray($data);
    }
}
