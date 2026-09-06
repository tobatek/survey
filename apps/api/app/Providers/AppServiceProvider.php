<?php

namespace App\Providers;

use App\Domains\Identity\Support\SsoCryptoService;
use Illuminate\Auth\Middleware\Authenticate;
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
    }
}
