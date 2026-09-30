<?php

namespace App\Providers;

use App\Domains\Identity\Support\SsoCryptoService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SsoCryptoService::class, function () {
            return new SsoCryptoService((string) config('sso.app_token'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Backend ini API-only (tidak ada halaman login Laravel — satu-
        // satunya login adalah redirect SSO lewat /auth/redirect, lihat
        // SsoController). Tanpa override ini, request tanpa header
        // `Accept: application/json` ke endpoint auth:sanctum akan 500
        // karena Authenticate::redirectTo() mencoba route('login') yang
        // tidak pernah didefinisikan. Mengembalikan null di sini membuat
        // Laravel langsung melempar AuthenticationException (401) tanpa
        // mencoba redirect.
        Authenticate::redirectUsing(fn () => null);

        // Fase 5 slice 7 (ditemukan saat demo export) -- tanpa ini, URL
        // absolut (mis. signed download URL export) dibentuk dari host
        // request yang MASUK, bukan APP_URL. Di dev, request ke /api lewat
        // proxy Vite tiba dengan Host: 127.0.0.1:8000 (lihat komentar
        // apps/web/vite.config.ts soal kenapa 127.0.0.1 dipaksa untuk
        // proxy target), padahal sesi Sanctum di-scope ke `localhost`
        // (SANCTUM_STATEFUL_DOMAINS) -- signed URL yang salah host jadi
        // tidak terautentikasi saat diklik dari SPA. Force ke APP_URL
        // supaya konsisten, sekaligus jadi pengaman standar kalau nanti
        // production ada di belakang reverse proxy yang mengubah Host.
        URL::forceRootUrl(config('app.url'));

        // Fase 6 paket A -- rate limiting (FSD §21.1 "rate limiting per
        // user/IP/token sesuai endpoint"). Sebelum ini TIDAK ADA rate
        // limiting sama sekali di aplikasi. Tiga limiter bernama, dipasang
        // ke rute yang jadi target realistis abuse:
        // - `sso`: callback SSO per IP -- cegah brute force/flood di titik
        //   masuk autentikasi.
        // - `start-by-token`: per IP -- startByToken menghash token input
        //   dan mencocokkan ke eligibility, target alami untuk tebak-tebak
        //   token undangan kalau tidak dibatasi.
        // - `submit`: per IP -- endpoint draft/submit publik (anonymous-
        //   capable, di luar grup auth:sanctum), limit longgar (cukup untuk
        //   pemakaian wajar satu responden) supaya tidak mengganggu jaringan
        //   kampus ber-NAT, tapi tetap membatasi automated spam.
        // - `exports`: per user login -- cegah spam POST /exports (tiap
        //   request men-trigger queue job, bukan gratis).
        RateLimiter::for('sso', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('start-by-token', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('submit', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(10)->by(Auth::id() ?? $request->ip()));
    }
}
