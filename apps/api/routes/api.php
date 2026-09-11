<?php

use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MatrixRowController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuestionnaireController;
use App\Http\Controllers\Api\QuestionnaireSectionController;
use App\Http\Controllers\Api\QuestionOptionController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SurveyTemplateController;
use App\Http\Controllers\Api\SyncRunController;
use App\Http\Controllers\Api\TemplateImportController;
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

    // Kuesioner builder (Fase 2, FSD §17.3) — lihat/list boleh executive_viewer
    // (agregat), tapi mutasi builder hanya LPM Admin & Survey Operator
    // (FSD §5.3). Semua mutasi wajib header If-Match (lock_version).
    Route::middleware($adminViewerRoles)->group(function () {
        Route::get('/questionnaires', [QuestionnaireController::class, 'index']);
        Route::get('/questionnaires/{questionnaire}', [QuestionnaireController::class, 'show']);
        Route::get('/questionnaires/{questionnaire}/validate', [QuestionnaireController::class, 'validateStructure']);
        Route::get('/templates', [SurveyTemplateController::class, 'index']);
    });

    Route::middleware('role:lpm_admin,survey_operator')->group(function () {
        Route::post('/questionnaires', [QuestionnaireController::class, 'store']);
        Route::patch('/questionnaires/{questionnaire}', [QuestionnaireController::class, 'update']);
        Route::delete('/questionnaires/{questionnaire}', [QuestionnaireController::class, 'destroy']);
        Route::post('/questionnaires/{questionnaire}/duplicate', [QuestionnaireController::class, 'duplicate']);
        Route::post('/questionnaires/{questionnaire}/finalize', [QuestionnaireController::class, 'finalize']);
        Route::post('/questionnaires/{questionnaire}/transition', [QuestionnaireController::class, 'transition']);

        Route::post('/questionnaires/{questionnaire}/sections', [QuestionnaireSectionController::class, 'store']);
        Route::patch('/sections/{section}', [QuestionnaireSectionController::class, 'update']);
        Route::delete('/sections/{section}', [QuestionnaireSectionController::class, 'destroy']);

        Route::post('/sections/{section}/questions', [QuestionController::class, 'store']);
        Route::patch('/questions/{question}', [QuestionController::class, 'update']);
        Route::delete('/questions/{question}', [QuestionController::class, 'destroy']);

        Route::post('/questions/{question}/options', [QuestionOptionController::class, 'store']);
        Route::patch('/options/{option}', [QuestionOptionController::class, 'update']);
        Route::delete('/options/{option}', [QuestionOptionController::class, 'destroy']);

        Route::post('/questions/{question}/matrix-rows', [MatrixRowController::class, 'store']);
        Route::patch('/matrix-rows/{matrixRow}', [MatrixRowController::class, 'update']);
        Route::delete('/matrix-rows/{matrixRow}', [MatrixRowController::class, 'destroy']);

        // Aktifkan/nonaktifkan template (FSD §17.7).
        Route::patch('/templates/{template}/status', [SurveyTemplateController::class, 'updateStatus']);
    });

    // Import Template Kuesioner (FSD §15, slice JSON) — lihat histori boleh
    // adminViewerRoles, upload & commit hanya LPM Admin & Survey Operator.
    Route::middleware($adminViewerRoles)->group(function () {
        Route::get('/settings/template-imports', [TemplateImportController::class, 'index']);
        Route::get('/settings/template-imports/{templateImport}', [TemplateImportController::class, 'show']);
    });

    Route::middleware('role:lpm_admin,survey_operator')->group(function () {
        Route::post('/settings/template-imports', [TemplateImportController::class, 'store']);
        Route::post('/settings/template-imports/{templateImport}/commit', [TemplateImportController::class, 'commit']);
    });
});
