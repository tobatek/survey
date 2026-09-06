<?php

namespace App\Http\Controllers\Auth;

use App\Domains\Audit\Actions\RecordAuditEventAction;
use App\Domains\Identity\Actions\FindOrCreateUserFromSsoIdentityAction;
use App\Domains\Identity\Support\PortalSsoClient;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

// Alur SSO lengkap — docs/15_SSO_PORTAL_VALIDASI_SEPARATION_PLAN.md
// "Ringkasan alur". myAMETA Survey TIDAK memiliki form login sendiri:
// satu-satunya jalur masuk adalah redirect() -> Portal -> callback() di sini.
//
// CATATAN: kontrak dokumen 15 tidak mendefinisikan parameter `state` yang
// di-echo balik oleh Portal, sehingga controller ini tidak bisa melakukan
// CSRF-state-check standar OAuth. Mitigasi yang tersedia hanyalah umur
// `code` yang sangat pendek (60 detik, sekali pakai, divalidasi di sisi
// Portal) — perlu dikonfirmasi ke tim Portal apakah `state` akan didukung.
class SsoController extends Controller
{
    public function redirect(PortalSsoClient $client): RedirectResponse
    {
        $redirectUri = config('sso.redirect_uri');

        return redirect()->away($client->authorizeUrl($redirectUri));
    }

    public function callback(
        Request $request,
        PortalSsoClient $client,
        FindOrCreateUserFromSsoIdentityAction $findOrCreateUser,
        RecordAuditEventAction $recordAuditEvent,
    ): RedirectResponse {
        $correlationId = (string) Str::uuid();
        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            $recordAuditEvent->execute(
                action: 'login_failed',
                result: 'missing_code',
                correlationId: $correlationId,
            );

            return $this->redirectToSpaWithError($correlationId);
        }

        try {
            $identity = $client->verify($code);
            $user = $findOrCreateUser->execute($identity);

            Auth::login($user);
            $request->session()->regenerate();

            $recordAuditEvent->execute(
                action: 'login_succeeded',
                result: 'success',
                actor: $user,
                correlationId: $correlationId,
            );

            return redirect()->away(config('services.spa.url'));
        } catch (RuntimeException $e) {
            Log::warning('SSO callback gagal', ['correlation_id' => $correlationId, 'error' => $e->getMessage()]);

            $recordAuditEvent->execute(
                action: 'login_failed',
                result: 'verify_failed',
                context: ['error' => $e->getMessage()],
                correlationId: $correlationId,
            );

            return $this->redirectToSpaWithError($correlationId);
        }
    }

    public function logout(Request $request, RecordAuditEventAction $recordAuditEvent): RedirectResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user !== null) {
            $recordAuditEvent->execute(action: 'logout', result: 'success', actor: $user);
        }

        return redirect()->away(config('services.spa.url'));
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user()?->loadMissing('roles'));
    }

    private function redirectToSpaWithError(string $correlationId): RedirectResponse
    {
        $spaUrl = rtrim(config('services.spa.url'), '/');

        return redirect()->away("{$spaUrl}/login?error=sso_failed&correlation_id={$correlationId}");
    }
}
