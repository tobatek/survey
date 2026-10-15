<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Domains\Questionnaires\Exceptions\QuestionnaireLockConflictException;
use App\Domains\Questionnaires\Models\Questionnaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

// Dipakai lintas controller builder Kuesioner (Questionnaire/Section/Question/
// Option) yang semuanya mengikuti pola If-Match + 409 yang sama (FSD §7.7).
// authorizeView/authorizeManage membungkus QuestionnairePolicy (resource-level
// — role + kecocokan tenant) sebagai pelengkap gerbang role kasar di middleware.
trait HandlesQuestionnaireLock
{
    private function authorizeView(Questionnaire $questionnaire): void
    {
        Gate::authorize('view', $questionnaire);
    }

    private function authorizeManage(Questionnaire $questionnaire): void
    {
        Gate::authorize('update', $questionnaire);
    }

    private function ifMatch(Request $request): ?int
    {
        $header = $request->header('If-Match');

        return $header !== null && is_numeric($header) ? (int) $header : null;
    }

    private function conflictResponse(QuestionnaireLockConflictException $e): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'lock_conflict',
                'message' => $e->getMessage(),
                'fields' => ['lock_version' => $e->currentLockVersion],
            ],
        ], 409);
    }
}
