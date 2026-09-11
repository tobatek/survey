<?php

namespace App\Http\Controllers\Api;

use App\Domains\Questionnaires\Exceptions\QuestionnaireLockConflictException;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\QuestionnaireSection;
use App\Domains\Questionnaires\Support\QuestionnaireLockGuard;
use App\Http\Controllers\Api\Concerns\HandlesQuestionnaireLock;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionnaireSectionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// CRUD section builder — bagian dari FSD §17.3. Semua mutasi wajib If-Match
// (lock_version questionnaire induk) dan hanya boleh saat questionnaire masih
// `draft` (lihat Questionnaire::isEditable()).
class QuestionnaireSectionController extends Controller
{
    use HandlesQuestionnaireLock;

    public function __construct(private readonly QuestionnaireLockGuard $lockGuard) {}

    public function store(Request $request, Questionnaire $questionnaire): JsonResponse
    {
        $this->authorizeManage($questionnaire);
        $this->abortIfNotEditable($questionnaire);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $section = DB::transaction(function () use ($questionnaire, $data, $request) {
            $this->lockGuard->assertMatches($questionnaire, $this->ifMatch($request));

            $section = $questionnaire->sections()->create([
                ...$data,
                'sort_order' => $data['sort_order'] ?? ($questionnaire->sections()->max('sort_order') + 1),
            ]);

            $this->lockGuard->bump($questionnaire);

            return $section;
        });

        return (new QuestionnaireSectionResource($section))->response()->setStatusCode(201);
    }

    public function update(Request $request, QuestionnaireSection $section): QuestionnaireSectionResource|JsonResponse
    {
        $questionnaire = $section->questionnaire;
        $this->authorizeManage($questionnaire);
        $this->abortIfNotEditable($questionnaire);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:100'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        try {
            DB::transaction(function () use ($questionnaire, $section, $data, $request) {
                $this->lockGuard->assertMatches($questionnaire, $this->ifMatch($request));
                $section->update($data);
                $this->lockGuard->bump($questionnaire);
            });
        } catch (QuestionnaireLockConflictException $e) {
            return $this->conflictResponse($e);
        }

        return new QuestionnaireSectionResource($section->fresh());
    }

    public function destroy(Request $request, QuestionnaireSection $section): JsonResponse
    {
        $questionnaire = $section->questionnaire;
        $this->authorizeManage($questionnaire);
        $this->abortIfNotEditable($questionnaire);

        try {
            DB::transaction(function () use ($questionnaire, $section, $request) {
                $this->lockGuard->assertMatches($questionnaire, $this->ifMatch($request));
                $section->delete();
                $this->lockGuard->bump($questionnaire);
            });
        } catch (QuestionnaireLockConflictException $e) {
            return $this->conflictResponse($e);
        }

        return response()->json(null, 204);
    }

    private function abortIfNotEditable(Questionnaire $questionnaire): void
    {
        abort_unless($questionnaire->isEditable(), 422, 'Kuesioner yang sudah difinalisasi tidak bisa diedit langsung.');
    }
}
