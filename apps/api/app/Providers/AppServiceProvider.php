<?php

namespace App\Providers;

use App\Domains\Identity\Support\SsoCryptoService;
use Illuminate\Auth\Middleware\Authenticate;
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
    }
}
