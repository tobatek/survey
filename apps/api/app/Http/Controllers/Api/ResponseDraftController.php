<?php

namespace App\Http\Controllers\Api;

use App\Domains\Respondents\Actions\LoadDraftAction;
use App\Domains\Respondents\Actions\SaveDraftAction;
use App\Domains\Respondents\Exceptions\DraftLockConflictException;
use App\Domains\Respondents\Exceptions\DraftValidationFailedException;
use App\Domains\Respondents\Exceptions\SurveyAccessException;
use App\Domains\Respondents\Models\ResponseSession;
use App\Http\Controllers\Api\Concerns\HandlesDraftLock;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// FSD §11.2/§11.3/§17.2 -- draft & autosave (Fase 4 slice 3). Route TIDAK
// dipasang di grup auth:sanctum karena mode anonymous mengakses tanpa login
// (lihat routes/api.php); otorisasi identified dilakukan manual lewat
// HandlesDraftLock::authorizeIdentifiedAccess().
class ResponseDraftController extends Controller
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

    public function save(Request $request, ResponseSession $session, SaveDraftAction $action): JsonResponse
    {
        $this->authorizeIdentifiedAccess($session);

        $data = $request->validate(['answers' => ['required', 'array']]);

        try {
            $session = $action->execute($session, $data['answers'], $this->ifMatch($request));
        } catch (SurveyAccessException $e) {
            return $this->accessErrorResponse($e);
        } catch (DraftLockConflictException $e) {
            return $this->conflictResponse($e);
        } catch (DraftValidationFailedException $e) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => $e->getMessage(),
                    'fields' => $e->errors,
                ],
            ], 422);
        }

        return response()->json(['data' => [
            'session_id' => $session->id,
            'lock_version' => $session->lock_version,
            'last_saved_at' => $session->last_saved_at?->toIso8601String(),
            'draft_answers' => $session->draft_answers_json ?? [],
        ]]);
    }

    private function accessErrorResponse(SurveyAccessException $e): JsonResponse
    {
        return response()->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], 422);
    }
}
