<?php

namespace App\Http\Controllers\Api;

use App\Domains\Questionnaires\Actions\CommitTemplateImportAction;
use App\Domains\Questionnaires\Actions\ValidateTemplateImportAction;
use App\Domains\Questionnaires\Exceptions\TemplateImportNotCommittableException;
use App\Domains\Questionnaires\Models\TemplateImport;
use App\Domains\Questionnaires\Support\TemplateSamplePayloadToXlsx;
use App\Http\Controllers\Controller;
use App\Http\Resources\SurveyTemplateResource;
use App\Http\Resources\TemplateImportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

// FSD §15 (slice JSON) — staging (store) selalu tersimpan baik lolos maupun
// gagal validasi (FSD §15.4), commit hanya berhasil untuk staging berstatus
// `validated`, belum expired, dan belum pernah di-commit. Gate::authorize
// (TemplateImportPolicy) menambah pemeriksaan resource-level (role + tenant)
// sebagai pelengkap gerbang role kasar di middleware routes/api.php.
class TemplateImportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TemplateImport::class);

        $perPage = min($request->integer('per_page', 15), 100);

        return TemplateImportResource::collection(
            TemplateImport::query()
                ->where('tenant_id', Auth::user()->tenant_id)
                ->with('uploader')
                ->orderByDesc('created_at')
                ->paginate($perPage),
        );
    }

    public function store(Request $request, ValidateTemplateImportAction $action): JsonResponse
    {
        Gate::authorize('create', TemplateImport::class);

        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'format' => ['sometimes', 'string', 'in:json,xlsx'],
        ]);

        $import = $action->execute($data['filename'], $data['content'], Auth::user(), $data['format'] ?? 'json');

        return (new TemplateImportResource($import))->response()->setStatusCode(201);
    }

    // Contoh file valid (FSD §17.7) supaya admin tidak coba-coba upload file
    // sembarangan (mis. kejadian upload airports.json yang jelas bukan skema
    // template). JSON dan XLSX dibangun dari data contoh yang sama
    // (sampleTemplatePayload) supaya keduanya tidak pernah tidak-sinkron.
    public function sample(): JsonResponse
    {
        Gate::authorize('viewAny', TemplateImport::class);

        return response()->json($this->sampleTemplatePayload(), 200, [
            'Content-Disposition' => 'attachment; filename="template-sample.json"',
        ]);
    }

    public function sampleXlsx(): StreamedResponse
    {
        Gate::authorize('viewAny', TemplateImport::class);

        $spreadsheet = (new TemplateSamplePayloadToXlsx)->build($this->sampleTemplatePayload());
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleTemplatePayload(): array
    {
        return [
            'schema_version' => '1.0',
            'template' => [
                'code' => 'CONTOH-001',
                'title' => 'Contoh Survei Kepuasan Mahasiswa',
                'description' => 'Ganti seluruh isi file ini sebelum diimpor sungguhan.',
                'category' => 'layanan_mahasiswa',
                'audience_type' => 'student',
                'identity_mode' => 'verified_anonymous',
                'language' => 'id',
            ],
            'sections' => [
                [
                    'code' => 'S1',
                    'title' => 'Data Diri',
                    'questions' => [
                        [
                            'code' => 'Q1',
                            'text' => 'Siapa nama lengkap Anda?',
                            'type' => 'short_text',
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'code' => 'S2',
                    'title' => 'Kepuasan Layanan',
                    'questions' => [
                        [
                            'code' => 'Q2',
                            'text' => 'Seberapa puas Anda dengan layanan akademik?',
                            'type' => 'single_choice',
                            'required' => true,
                            'options' => [
                                ['code' => 'O1', 'label' => 'Sangat Puas', 'numeric_score' => 4],
                                ['code' => 'O2', 'label' => 'Puas', 'numeric_score' => 3],
                                ['code' => 'O3', 'label' => 'Tidak Puas', 'numeric_score' => 1],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function show(TemplateImport $templateImport): TemplateImportResource
    {
        Gate::authorize('view', $templateImport);

        return new TemplateImportResource($templateImport->load('uploader'));
    }

    public function commit(TemplateImport $templateImport, CommitTemplateImportAction $action): JsonResponse
    {
        Gate::authorize('commit', $templateImport);

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
