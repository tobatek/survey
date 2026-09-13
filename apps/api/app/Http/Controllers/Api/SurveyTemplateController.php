<?php

namespace App\Http\Controllers\Api;

use App\Domains\Questionnaires\Actions\ExportTemplateAction;
use App\Domains\Questionnaires\Models\SurveyTemplate;
use App\Http\Controllers\Controller;
use App\Http\Resources\SurveyTemplateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

// Daftar template untuk dipilih saat "Buat Kuesioner dari Template"
// (QuestionnaireListPage.vue, default cuma `active`) dan untuk halaman kelola
// template (TemplateImportPage.vue, `?status=all`). Struktur template diisi
// lewat Import Template (FSD §15, lihat CommitTemplateImportAction).
// updateStatus = aktifkan/nonaktifkan (FSD §17.7 PATCH /templates/{id}/status).
// export = unduh JSON template (kebalikan Import, lihat ExportTemplateAction).
class SurveyTemplateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SurveyTemplate::class);

        $query = SurveyTemplate::query()->where('tenant_id', Auth::user()->tenant_id);

        $status = $request->string('status')->value();
        if ($status !== 'all') {
            $query->where('status', $status !== '' ? $status : 'active');
        }

        return SurveyTemplateResource::collection($query->orderBy('title')->get());
    }

    public function updateStatus(Request $request, SurveyTemplate $template): SurveyTemplateResource
    {
        Gate::authorize('update', $template);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $template->update($data);

        return new SurveyTemplateResource($template->fresh());
    }

    public function export(SurveyTemplate $template, ExportTemplateAction $action): JsonResponse
    {
        Gate::authorize('view', $template);

        return response()->json($action->execute($template), 200, [
            'Content-Disposition' => "attachment; filename=\"{$template->code}.json\"",
        ]);
    }
}
