<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MatrixRowController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuestionnaireController;
use App\Http\Controllers\Api\QuestionnaireSectionController;
use App\Http\Controllers\Api\QuestionOptionController;
use App\Http\Controllers\Api\RespondentSurveyController;
use App\Http\Controllers\Api\ResponseController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SurveyTemplateController;
use App\Http\Controllers\Api\SyncRunController;
use App\Http\Controllers\Api\TemplateImportController;
use App\Http\Controllers\Auth\SsoController;
use Illuminate\Support\Facades\Route;

Route::get('/health/ready', [HealthController::class, 'ready']);

// FSD §11 jalur anonymous -- link token undangan, tidak pernah login.
Route::post('/my-surveys/start-by-token', [RespondentSurveyController::class, 'startByToken']);

// FSD §11.2/§11.3/§11.4/§17.2 -- draft, autosave & submit (Fase 4 slice 3-4).
// Sengaja di luar grup auth:sanctum supaya mode anonymous (tanpa login)
// tetap bisa akses; otorisasi mode identified dicek manual di controller
// (lihat Concerns\HandlesDraftLock) karena statefulApi() tetap mengisi
// Auth::user() dari cookie sesi walau route ini tidak diproteksi middleware
// auth:sanctum.
Route::get('/responses/{session}', [ResponseController::class, 'show']);
Route::put('/responses/{session}/draft', [ResponseController::class, 'saveDraft']);
Route::post('/responses/{session}/submit', [ResponseController::class, 'submit']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [SsoController::class, 'me']);

    // FSD §11 jalur identified -- boleh diakses siapa saja yang login,
    // bukan cuma role admin (dipakai mahasiswa/pegawai biasa).
    Route::get('/me/surveys', [RespondentSurveyController::class, 'mySurveys']);
    Route::post('/my-surveys/{eligibility}/start', [RespondentSurveyController::class, 'start']);

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
        Route::get('/templates/{template}/export', [SurveyTemplateController::class, 'export']);
        Route::get('/campaigns/{campaign}/monitoring', [CampaignController::class, 'monitoring']);
        Route::get('/campaigns/{campaign}', [CampaignController::class, 'show']);
        Route::get('/questionnaires/{questionnaire}/campaigns', [CampaignController::class, 'indexForQuestionnaire']);

        // Hasil Analisa Kuesioner (Fase 5 slice 2, FSD §17.6) -- query
        // langsung, belum ada filter/threshold per-pertanyaan (slice 3).
        Route::get('/analytics/campaigns', [AnalyticsController::class, 'index']);
        Route::get('/analytics/campaigns/{campaign}/summary', [AnalyticsController::class, 'summary']);
        Route::get('/analytics/campaigns/{campaign}/questions', [AnalyticsController::class, 'questions']);

        // Export async dasar (Fase 5 slice 5, FSD §14.2/§17.6) -- hanya
        // xlsx sekarang, CSV/PDF/PNG slice 6. Download lewat rute terpisah
        // di luar grup ini (butuh middleware `signed` tambahan, lihat bawah).
        Route::post('/exports', [ExportController::class, 'store']);
        Route::get('/exports/{export}', [ExportController::class, 'show']);
    });

    // Signed route terpisah dari grup adminViewerRoles di atas supaya
    // middleware `signed` (validasi HMAC+expiry query string) berjalan
    // BERSAMA middleware role -- bukan pengganti otorisasi Gate, tapi
    // lapisan tambahan (FSD §21.2 "otorisasi diperiksa sebelum download"
    // sebagai poin terpisah dari sekadar signed URL).
    Route::middleware([$adminViewerRoles, 'signed'])
        ->get('/exports/{export}/download', [ExportController::class, 'download'])
        ->name('exports.download');

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

        // Campaign (Fase 3, FSD §17.4) — slice 1: hanya pembuatan campaign
        // draft dari questionnaire yang sudah finalized. Endpoint lain
        // (preview/activate/pause/resume/close/monitoring/reminders)
        // menyusul di slice berikutnya.
        Route::post('/questionnaires/{questionnaire}/campaigns', [CampaignController::class, 'store']);
        Route::patch('/campaigns/{campaign}/schedule', [CampaignController::class, 'updateSchedule']);
        Route::post('/campaigns/{campaign}/audience/preview', [CampaignController::class, 'previewAudience']);
        Route::post('/campaigns/{campaign}/activate', [CampaignController::class, 'activate']);
        Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause']);
        Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume']);
        Route::post('/campaigns/{campaign}/close', [CampaignController::class, 'close']);
    });

    // Import Template Kuesioner (FSD §15, slice JSON) — lihat histori boleh
    // adminViewerRoles, upload & commit hanya LPM Admin & Survey Operator.
    Route::middleware($adminViewerRoles)->group(function () {
        Route::get('/settings/template-imports', [TemplateImportController::class, 'index']);
        Route::get('/settings/template-imports/sample.json', [TemplateImportController::class, 'sample']);
        Route::get('/settings/template-imports/sample.xlsx', [TemplateImportController::class, 'sampleXlsx']);
        Route::get('/settings/template-imports/{templateImport}', [TemplateImportController::class, 'show'])->whereUuid('templateImport');
    });

    Route::middleware('role:lpm_admin,survey_operator')->group(function () {
        Route::post('/settings/template-imports', [TemplateImportController::class, 'store']);
        Route::post('/settings/template-imports/{templateImport}/commit', [TemplateImportController::class, 'commit']);
    });
});
