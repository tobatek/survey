<?php

use App\Http\Controllers\Auth\SsoController;
use Illuminate\Support\Facades\Route;

// Rute browser murni (bukan dipanggil via fetch/XHR SPA) — alur redirect
// penuh ke/dari Portal SSO. Lihat docs/15_SSO_PORTAL_VALIDASI_SEPARATION_PLAN.md.
// myAMETA Survey tidak memiliki halaman login sendiri di sini; /auth/redirect
// langsung membawa browser ke Portal.
Route::get('/auth/redirect', [SsoController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/callback', [SsoController::class, 'callback'])->name('auth.callback');
Route::post('/logout', [SsoController::class, 'logout'])->name('auth.logout');
