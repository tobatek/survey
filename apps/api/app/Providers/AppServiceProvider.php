<?php

namespace App\Providers;

use App\Domains\Identity\Support\SsoCryptoService;
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
        //
    }
}
