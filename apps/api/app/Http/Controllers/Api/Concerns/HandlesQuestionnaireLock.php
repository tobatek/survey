<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Domains\Questionnaires\Exceptions\QuestionnaireLockConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Dipakai lintas controller builder Kuesioner (Questionnaire/Section/Question/
// Option) yang semuanya mengikuti pola If-Match + 409 yang sama (FSD §7.7).
trait HandlesQuestionnaireLock
{
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
