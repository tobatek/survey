<?php

namespace App\Http\Controllers\Api;

use App\Domains\Questionnaires\Actions\CommitTemplateImportAction;
use App\Domains\Questionnaires\Actions\ValidateTemplateImportAction;
use App\Domains\Questionnaires\Exceptions\TemplateImportNotCommittableException;
use App\Domains\Questionnaires\Models\TemplateImport;
use App\Http\Controllers\Controller;
use App\Http\Resources\SurveyTemplateResource;
use App\Http\Resources\TemplateImportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

// FSD §15 (slice JSON) — staging (store) selalu tersimpan baik lolos maupun
// gagal validasi (FSD §15.4), commit hanya berhasil untuk staging berstatus
// `validated`, belum expired, dan belum pernah di-commit.
class TemplateImportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 15), 100);

        return TemplateImportResource::collection(
            TemplateImport::query()->with('uploader')->orderByDesc('created_at')->paginate($perPage),
        );
    }

    public function store(Request $request, ValidateTemplateImportAction $action): JsonResponse
    {
        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $import = $action->execute($data['filename'], $data['content'], Auth::user());

        return (new TemplateImportResource($import))->response()->setStatusCode(201);
    }

    public function show(TemplateImport $templateImport): TemplateImportResource
    {
        return new TemplateImportResource($templateImport->load('uploader'));
    }

    public function commit(TemplateImport $templateImport, CommitTemplateImportAction $action): JsonResponse
    {
        try {
            $template = $action->execute($templateImport, Auth::user());
        } catch (TemplateImportNotCommittableException $e) {
            return response()->json([
                'error' => [
                    'code' => $e->reasonCode,
                    'message' => $e->getMessage(),
                    'fields' => $e->errors,
                ],
            ], 422);
        }

        return (new SurveyTemplateResource($template))->response()->setStatusCode(201);
    }
}
