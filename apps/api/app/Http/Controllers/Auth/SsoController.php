<?php

namespace App\Http\Controllers\Auth;

use App\Domains\Audit\Actions\RecordAuditEventAction;
use App\Domains\Identity\Actions\FindOrCreateUserFromSsoIdentityAction;
use App\Domains\Identity\Support\PortalSsoClient;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            // Dibuka langsung (mis. dari tile Portal) tanpa code/error: mulai alur dari awal.
            // Flag sesi mencegah loop kalau Portal terus membalik tanpa code.
            if (! $request->has('error') && ! $request->session()->has('sso_restarted_at')) {
                $request->session()->put('sso_restarted_at', now()->timestamp);
                Log::info('SSO callback tanpa code, alur dimulai ulang', ['correlation_id' => $correlationId]);

                return redirect()->route('auth.redirect');
            }

            return $this->failLogin($recordAuditEvent, $correlationId, 'code_missing', 'missing_code');
        }

        try {
            $identity = $client->verify($code);
        } catch (RuntimeException $e) {
            $reason = str_contains($e->getMessage(), 'ditolak') ? 'code_expired_or_rejected' : 'token_exchange_failed';

            return $this->failLogin($recordAuditEvent, $correlationId, $reason, 'verify_failed', $e->getMessage());
        }

        try {
            $user = $findOrCreateUser->execute($identity);
        } catch (RuntimeException $e) {
            return $this->failLogin($recordAuditEvent, $correlationId, 'user_not_found', 'verify_failed', $e->getMessage());
        }

        $request->session()->forget('sso_restarted_at');
        Auth::login($user);
        $request->session()->regenerate();

        $recordAuditEvent->execute(
            action: 'login_succeeded',
            result: 'success',
            actor: $user,
            correlationId: $correlationId,
        );

        return redirect()->away(config('services.spa.url'));
    }

    private function failLogin(
        RecordAuditEventAction $recordAuditEvent,
        string $correlationId,
        string $reason,
        string $auditResult,
        ?string $detail = null,
    ): RedirectResponse {
        // Tanpa code/token/cookie: hanya alasan, kode referensi, dan pesan galat.
        Log::warning('SSO callback gagal', array_filter([
            'reason' => $reason,
            'correlation_id' => $correlationId,
            'error' => $detail,
        ]));

        $recordAuditEvent->execute(
            action: 'login_failed',
            result: $auditResult,
            context: ['reason' => $reason],
            correlationId: $correlationId,
        );

        return $this->redirectToSpaWithError($correlationId);
    }

    // Dev-only: bypass Portal SSO, login langsung sebagai user existing.
    // Rute pemanggilnya (routes/web.php) hanya terdaftar saat app()->environment('local'),
    // tapi guard di-cek ulang di sini sebagai lapisan kedua.
    public function devLogin(Request $request, RecordAuditEventAction $recordAuditEvent): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $email = $request->query('email', 'admin@myameta-survey.test');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            abort(404, "User dev-login dengan email {$email} tidak ditemukan. Jalankan `php artisan db:seed`.");
        }

        Auth::login($user);
        $request->session()->regenerate();

        $recordAuditEvent->execute(
            action: 'login_succeeded',
            result: 'success',
            actor: $user,
            correlationId: (string) Str::uuid(),
        );

        return redirect()->away(config('services.spa.url'));
    }

    public function logout(Request $request, RecordAuditEventAction $recordAuditEvent): RedirectResponse|Response
    {
        $user = $request->user();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user !== null) {
            $recordAuditEvent->execute(action: 'logout', result: 'success', actor: $user);
        }

        // SPA memanggil lewat fetch lintas-origin: balasan redirect ke host SPA
        // akan ditolak browser (CORS), jadi cukup 204 dan SPA yang berpindah halaman.
        if ($request->expectsJson()) {
            return response()->noContent();
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
