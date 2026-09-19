<?php

namespace App\Http\Controllers\Api;

use App\Domains\Campaigns\Actions\ActivateCampaignAction;
use App\Domains\Campaigns\Actions\CampaignMonitoringAction;
use App\Domains\Campaigns\Actions\CreateCampaignAction;
use App\Domains\Campaigns\Actions\PreviewAudienceAction;
use App\Domains\Campaigns\Actions\TransitionCampaignStatusAction;
use App\Domains\Campaigns\DTO\AudienceFilterDTO;
use App\Domains\Campaigns\Exceptions\CampaignActivationInProgressException;
use App\Domains\Campaigns\Exceptions\CampaignNotActivatableException;
use App\Domains\Campaigns\Exceptions\CampaignTransitionConflictException;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

// FSD §17.4 — endpoint Campaign (Fase 3). Campaign hanya boleh dibuat dari
// questionnaire yang sudah difinalisasi (status ready+), karena campaign
// menunjuk snapshot immutable-nya saat aktivasi.
class CampaignController extends Controller
{
    public function store(Questionnaire $questionnaire, CreateCampaignAction $action): JsonResponse
    {
        Gate::authorize('create', Campaign::class);

        $actor = Auth::user();

        abort_if($questionnaire->tenant_id !== $actor->tenant_id, 404);

        abort_if(
            $questionnaire->status === Questionnaire::STATUS_DRAFT,
            422,
            'Kuesioner harus difinalisasi (status ready atau setelahnya) sebelum campaign dibuat.',
        );

        $campaign = $action->execute($questionnaire, $actor);

        return (new CampaignResource($campaign))->response()->setStatusCode(201);
    }

    public function show(Campaign $campaign): CampaignResource
    {
        Gate::authorize('view', $campaign);

        return new CampaignResource($campaign);
    }

    public function indexForQuestionnaire(Questionnaire $questionnaire): JsonResponse
    {
        Gate::authorize('viewAny', Campaign::class);

        abort_if($questionnaire->tenant_id !== Auth::user()->tenant_id, 404);

        return CampaignResource::collection($questionnaire->campaigns()->get())->response();
    }

    public function updateSchedule(Request $request, Campaign $campaign): CampaignResource|JsonResponse
    {
        Gate::authorize('manage', $campaign);

        abort_if(
            $campaign->status !== Campaign::STATUS_DRAFT,
            422,
            'Jadwal hanya bisa diubah selama campaign berstatus draft.',
        );

        $data = $request->validate([
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'string', 'max:100'],
        ]);

        $campaign->update($data);

        return new CampaignResource($campaign->fresh());
    }

    public function previewAudience(Request $request, Campaign $campaign, PreviewAudienceAction $action): JsonResponse
    {
        Gate::authorize('manage', $campaign);

        abort_if(
            $campaign->status !== Campaign::STATUS_DRAFT,
            422,
            'Target hanya bisa diubah selama campaign berstatus draft.',
        );

        $data = $request->validate([
            'faculty_names' => ['sometimes', 'array'],
            'faculty_names.*' => ['string'],
            'program_study_codes' => ['sometimes', 'array'],
            'program_study_codes.*' => ['string'],
            'degree_levels' => ['sometimes', 'array'],
            'degree_levels.*' => ['string'],
            'cohort_years' => ['sometimes', 'array'],
            'cohort_years.*' => ['string'],
            'student_statuses' => ['sometimes', 'array'],
            'student_statuses.*' => ['string'],
            'employee_types' => ['sometimes', 'array'],
            'employee_types.*' => ['string'],
            'work_unit_names' => ['sometimes', 'array'],
            'work_unit_names.*' => ['string'],
            'employee_statuses' => ['sometimes', 'array'],
            'employee_statuses.*' => ['string'],
            'position_names' => ['sometimes', 'array'],
            'position_names.*' => ['string'],
            'include_student_ids' => ['sometimes', 'array'],
            'include_student_ids.*' => ['uuid'],
            'exclude_student_ids' => ['sometimes', 'array'],
            'exclude_student_ids.*' => ['uuid'],
            'include_employee_ids' => ['sometimes', 'array'],
            'include_employee_ids.*' => ['uuid'],
            'exclude_employee_ids' => ['sometimes', 'array'],
            'exclude_employee_ids.*' => ['uuid'],
        ]);

        // FSD §9/§10.1 — filter jabatan sensitif, tidak otomatis tersedia
        // untuk semua operator. Ditolak eksplisit alih-alih diam-diam
        // diabaikan, supaya survey_operator tahu kenapa hasilnya berbeda.
        if (! empty($data['position_names']) && ! Auth::user()->loadMissing('roles')->hasRole(Role::LPM_ADMIN)) {
            return response()->json([
                'error' => [
                    'code' => 'position_filter_not_allowed',
                    'message' => 'Filter jabatan hanya bisa dipakai oleh LPM Admin.',
                ],
            ], 422);
        }

        $filter = AudienceFilterDTO::fromArray($data);

        $result = $action->execute($campaign, $filter);

        $campaign->update(['target_rule_json' => $filter->toArray()]);

        return response()->json(['data' => $result]);
    }

    public function activate(Campaign $campaign, ActivateCampaignAction $action): CampaignResource|JsonResponse
    {
        Gate::authorize('manage', $campaign);

        try {
            $campaign = $action->execute($campaign, Auth::user());
        } catch (CampaignNotActivatableException $e) {
            return response()->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], 422);
        } catch (CampaignActivationInProgressException $e) {
            return response()->json(['error' => ['code' => 'activation_in_progress', 'message' => $e->getMessage()]], 409);
        }

        return new CampaignResource($campaign);
    }

    public function monitoring(Campaign $campaign, CampaignMonitoringAction $action): JsonResponse
    {
        Gate::authorize('view', $campaign);

        return response()->json(['data' => $action->execute($campaign)]);
    }

    public function pause(Campaign $campaign, TransitionCampaignStatusAction $action): CampaignResource|JsonResponse
    {
        return $this->transition($campaign, Campaign::STATUS_PAUSED, $action);
    }

    public function resume(Campaign $campaign, TransitionCampaignStatusAction $action): CampaignResource|JsonResponse
    {
        return $this->transition($campaign, Campaign::STATUS_ACTIVE, $action);
    }

    public function close(Campaign $campaign, TransitionCampaignStatusAction $action): CampaignResource|JsonResponse
    {
        return $this->transition($campaign, Campaign::STATUS_CLOSED, $action);
    }

    private function transition(Campaign $campaign, string $targetStatus, TransitionCampaignStatusAction $action): CampaignResource|JsonResponse
    {
        Gate::authorize('manage', $campaign);

        try {
            $campaign = $action->execute($campaign, $targetStatus, Auth::user());
        } catch (CampaignTransitionConflictException $e) {
            return response()->json(['error' => ['code' => 'transition_conflict', 'message' => $e->getMessage()]], 409);
        } catch (RuntimeException $e) {
            return response()->json(['error' => ['code' => 'invalid_transition', 'message' => $e->getMessage()]], 422);
        }

        return new CampaignResource($campaign);
    }
}
