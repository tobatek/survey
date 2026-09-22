<?php

namespace App\Http\Controllers\Api;

use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Respondents\Actions\ListMySurveysAction;
use App\Domains\Respondents\Actions\StartResponseSessionAction;
use App\Domains\Respondents\Exceptions\SurveyAccessException;
use App\Domains\Respondents\Models\ResponseSession;
use App\Domains\Respondents\Support\ResolveAudienceSubject;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

// FSD §11 & §17.2 -- jalur masuk responden ke survei: login (identified,
// lewat halaman Survei Saya) dan link token undangan (anonymous, publik
// tanpa login). Lihat StartResponseSessionAction untuk logika bersama.
class RespondentSurveyController extends Controller
{
    public function mySurveys(Request $request, ListMySurveysAction $action): JsonResponse
    {
        return response()->json(['data' => $action->execute(Auth::user())]);
    }

    public function start(Eligibility $eligibility, StartResponseSessionAction $action, ResolveAudienceSubject $resolveSubject): JsonResponse
    {
        Gate::authorize('start', $eligibility);

        $subject = $resolveSubject->forUser(Auth::user());

        try {
            $session = $action->execute($eligibility, ResponseSession::MODE_IDENTIFIED, $subject);
        } catch (SurveyAccessException $e) {
            return response()->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], 422);
        }

        return $this->sessionResponse($session);
    }

    public function startByToken(Request $request, StartResponseSessionAction $action): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string']]);

        $eligibility = Eligibility::query()
            ->where('invitation_token_hash', hash('sha256', $data['token']))
            ->first();

        if ($eligibility === null) {
            return response()->json(['error' => ['code' => 'NOT_ELIGIBLE', 'message' => 'Tautan undangan tidak valid.']], 404);
        }

        try {
            $session = $action->execute($eligibility, ResponseSession::MODE_ANONYMOUS, null, $data['token']);
        } catch (SurveyAccessException $e) {
            return response()->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], 422);
        }

        return $this->sessionResponse($session);
    }

    private function sessionResponse(ResponseSession $session): JsonResponse
    {
        return response()->json(['data' => [
            'session_id' => $session->id,
            'mode' => $session->mode,
            'status' => $session->status,
            'expires_at' => $session->expires_at?->toIso8601String(),
        ]]);
    }
}
