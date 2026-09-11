<?php

namespace App\Http\Controllers\Api;

use App\Domains\Questionnaires\Models\SurveyTemplate;
use App\Http\Controllers\Controller;
use App\Http\Resources\SurveyTemplateResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

// Daftar template aktif untuk dipilih saat "Buat Kuesioner dari Template"
// (QuestionnaireListPage.vue). Struktur template diisi lewat Import Template
// (FSD §15, lihat CommitTemplateImportAction).
class SurveyTemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $templates = SurveyTemplate::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('status', 'active')
            ->orderBy('title')
            ->get();

        return SurveyTemplateResource::collection($templates);
    }
}
