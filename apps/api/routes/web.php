<?php

use App\Http\Controllers\Auth\SsoController;
use Illuminate\Support\Facades\Route;

// Rute browser murni (bukan dipanggil via fetch/XHR SPA) — alur redirect
// penuh ke/dari Portal SSO. Lihat docs/15_SSO_PORTAL_VALIDASI_SEPARATION_PLAN.md.
// myAMETA Survey tidak memiliki halaman login sendiri di sini; /auth/redirect
// langsung membawa browser ke Portal.
Route::get('/auth/redirect', [SsoController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/callback', [SsoController::class, 'callback'])->middleware('throttle:sso')->name('auth.callback');
Route::post('/logout', [SsoController::class, 'logout'])->name('auth.logout');

// Bypass Portal SSO untuk dev lokal saja — TIDAK pernah terdaftar di
// environment selain local. Login langsung sebagai user existing lewat
// ?email=, default ke admin seed lokal (lihat DatabaseSeeder).
if (app()->environment('local')) {
    Route::get('/auth/dev-login', [SsoController::class, 'devLogin'])->name('auth.dev-login');
}
