<?php

namespace App\Http\Controllers\Api;

use App\Domains\Respondents\Actions\LoadDraftAction;
use App\Domains\Respondents\Actions\SaveDraftAction;
use App\Domains\Respondents\Actions\SubmitResponseAction;
use App\Domains\Respondents\Exceptions\AnswerValidationFailedException;
use App\Domains\Respondents\Exceptions\DraftLockConflictException;
use App\Domains\Respondents\Exceptions\SurveyAccessException;
use App\Domains\Respondents\Models\ResponseSession;
use App\Http\Controllers\Api\Concerns\HandlesDraftLock;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// FSD §11.2/§11.3/§11.4/§17.2 -- satu controller menaungi seluruh siklus
// resource "response" atas sebuah sesi (lihat draft, autosave, submit final),
// mengikuti pola CampaignController yang menaungi semua aksi atas satu
// resource dalam satu class. Route TIDAK dipasang di grup auth:sanctum
// karena mode anonymous mengakses tanpa login (lihat routes/api.php);
// otorisasi identified dilakukan manual lewat
// HandlesDraftLock::authorizeIdentifiedAccess().
class ResponseController extends Controller
{
    use HandlesDraftLock;

    public function show(ResponseSession $session, LoadDraftAction $action): JsonResponse
    {
        $this->authorizeIdentifiedAccess($session);

        try {
            $data = $action->execute($session);
        } catch (SurveyAccessException $e) {
            return $this->accessErrorResponse($e);
        }

        return response()->json(['data' => $data]);
    }

    public function saveDraft(Request $request, ResponseSession $session, SaveDraftAction $action): JsonResponse
    {
        $this->authorizeIdentifiedAccess($session);

        $data = $request->validate(['answers' => ['required', 'array']]);

        try {
            $session = $action->execute($session, $data['answers'], $this->ifMatch($request));
        } catch (SurveyAccessException $e) {
            return $this->accessErrorResponse($e);
        } catch (DraftLockConflictException $e) {
            return $this->conflictResponse($e);
        } catch (AnswerValidationFailedException $e) {
            return $this->validationErrorResponse($e);
        }

        return response()->json(['data' => [
            'session_id' => $session->id,
            'lock_version' => $session->lock_version,
            'last_saved_at' => $session->last_saved_at?->toIso8601String(),
            'draft_answers' => $session->draft_answers_json ?? [],
        ]]);
    }

    public function submit(Request $request, ResponseSession $session, SubmitResponseAction $action): JsonResponse
    {
        $this->authorizeIdentifiedAccess($session);

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Header Idempotency-Key wajib dikirim.',
                    'fields' => ['idempotency_key' => 'Header Idempotency-Key wajib dikirim.'],
                ],
            ], 422);
        }

        $data = $request->validate([
            'token' => [$session->mode === ResponseSession::MODE_ANONYMOUS ? 'required' : 'nullable', 'string'],
        ]);

        try {
            $response = $action->execute($session, $idempotencyKey, $data['token'] ?? null);
        } catch (SurveyAccessException $e) {
            return $this->accessErrorResponse($e);
        } catch (AnswerValidationFailedException $e) {
            return $this->validationErrorResponse($e);
        }

        return response()->json(['data' => [
            'response_id' => $response->id,
            'submitted_at' => $response->submitted_at->toIso8601String(),
        ]]);
    }

    private function accessErrorResponse(SurveyAccessException $e): JsonResponse
    {
        $status = $e->errorCode === 'SUBMISSION_IN_PROGRESS' ? 409 : 422;

        return response()->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], $status);
    }

    private function validationErrorResponse(AnswerValidationFailedException $e): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'VALIDATION_FAILED',
                'message' => $e->getMessage(),
                'fields' => $e->errors,
            ],
        ], 422);
    }
}
