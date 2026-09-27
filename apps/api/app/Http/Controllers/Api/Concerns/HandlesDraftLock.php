<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Domains\Respondents\Exceptions\DraftLockConflictException;
use App\Domains\Respondents\Models\ResponseSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

// Dipakai ResponseDraftController -- meniru pola
// App\Http\Controllers\Api\Concerns\HandlesQuestionnaireLock (If-Match/409),
// ditambah pembagian otorisasi identified vs anonymous (FSD §11 & §17.2):
// route draft ada DI LUAR middleware auth:sanctum supaya mode anonymous
// (tanpa login) tetap bisa akses, jadi mode identified perlu dicek manual
// di sini alih-alih lewat gerbang middleware.
trait HandlesDraftLock
{
    private function authorizeIdentifiedAccess(ResponseSession $session): void
    {
        if ($session->mode !== ResponseSession::MODE_IDENTIFIED) {
            // Mode anonymous: UUID sesi sendiri adalah kapabilitas akses,
            // sama seperti token undangan (lihat StartResponseSessionAction).
            return;
        }

        if (! Auth::check()) {
            throw new AuthorizationException('Anda harus login untuk mengakses draft ini.');
        }

        Gate::authorize('access', $session);
    }

    private function ifMatch(Request $request): ?int
    {
        $header = $request->header('If-Match');

        return $header !== null && is_numeric($header) ? (int) $header : null;
    }

    private function conflictResponse(DraftLockConflictException $e): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'STALE_DRAFT',
                'message' => $e->getMessage(),
                'fields' => ['lock_version' => $e->currentLockVersion],
            ],
        ], 409);
    }
}
