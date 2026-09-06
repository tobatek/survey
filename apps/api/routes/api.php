<?php

use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SyncRunController;
use App\Http\Controllers\Auth\SsoController;
use Illuminate\Support\Facades\Route;

Route::get('/health/ready', [HealthController::class, 'ready']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [SsoController::class, 'me']);

    // FSD §5.3 tabel otorisasi: data mahasiswa/pegawai bisa dilihat admin,
    // operator (scope terbatas — belum diberlakukan di Fase 1), dan viewer
    // (agregat) — bukan responden.
    $adminViewerRoles = 'role:lpm_admin,survey_operator,executive_viewer,technical_admin';

    Route::middleware($adminViewerRoles)->group(function () {
        Route::get('/students', [StudentController::class, 'index']);
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::get('/sync-runs', [SyncRunController::class, 'index']);
        Route::get('/sync-runs/{syncRun}', [SyncRunController::class, 'show']);
    });

    // Sync master data: hanya LPM Admin dan Technical Admin (BR-DM-005,
    // FSD §5.3 "Sync master: opsional / ya").
    Route::middleware('role:lpm_admin,technical_admin')->group(function () {
        Route::post('/students/sync', [StudentController::class, 'sync']);
        Route::post('/employees/sync', [EmployeeController::class, 'sync']);
    });
});
