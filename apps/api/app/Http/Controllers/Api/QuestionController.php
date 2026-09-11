<?php

namespace App\Http\Controllers\Api;

use App\Domains\Questionnaires\Exceptions\QuestionnaireLockConflictException;
use App\Domains\Questionnaires\Models\Question;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\QuestionnaireSection;
use App\Domains\Questionnaires\Support\QuestionnaireLockGuard;
use App\Http\Controllers\Api\Concerns\HandlesQuestionnaireLock;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// CRUD pertanyaan builder — FSD §17.3/§7.3. Tipe pertanyaan divalidasi
// terhadap Question::TYPES di sini (per-field), sedangkan aturan silang
// (opsi wajib untuk choice types, dst.) dicek belakangan oleh
// ValidateQuestionnaireStructureAction saat validate/finalize.
class QuestionController extends Controller
{
    use HandlesQuestionnaireLock;

    public function __construct(private readonly QuestionnaireLockGuard $lockGuard) {}

    public function store(Request $request, QuestionnaireSection $section): JsonResponse
    {
        $questionnaire = $section->questionnaire;
        $this->authorizeManage($questionnaire);
        $this->abortIfNotEditable($questionnaire);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'in:'.implode(',', Question::TYPES)],
            'text' => ['required', 'string'],
            'help_text' => ['nullable', 'string'],
            'required' => ['sometimes', 'boolean'],
            'allow_not_applicable' => ['sometimes', 'boolean'],
            'validation_json' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $question = DB::transaction(function () use ($questionnaire, $section, $data, $request) {
            $this->lockGuard->assertMatches($questionnaire, $this->ifMatch($request));

            $question = $section->questions()->create([
                ...$data,
                'questionnaire_id' => $questionnaire->id,
                'sort_order' => $data['sort_order'] ?? ($section->questions()->max('sort_order') + 1),
            ]);

            $this->lockGuard->bump($questionnaire);

            return $question->fresh();
        });

        return (new QuestionResource($question))->response()->setStatusCode(201);
    }

    public function update(Request $request, Question $question): QuestionResource|JsonResponse
    {
        $questionnaire = $question->questionnaire;
        $this->authorizeManage($questionnaire);
        $this->abortIfNotEditable($questionnaire);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', Question::TYPES)],
            'text' => ['sometimes', 'string'],
            'help_text' => ['nullable', 'string'],
            'required' => ['sometimes', 'boolean'],
            'allow_not_applicable' => ['sometimes', 'boolean'],
            'validation_json' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        try {
            DB::transaction(function () use ($questionnaire, $question, $data, $request) {
                $this->lockGuard->assertMatches($questionnaire, $this->ifMatch($request));
                $question->update($data);
                $this->lockGuard->bump($questionnaire);
            });
        } catch (QuestionnaireLockConflictException $e) {
            return $this->conflictResponse($e);
        }

        return new QuestionResource($question->fresh(['options', 'matrixRows']));
    }

    public function destroy(Request $request, Question $question): JsonResponse
    {
        $questionnaire = $question->questionnaire;
        $this->authorizeManage($questionnaire);
        $this->abortIfNotEditable($questionnaire);

        try {
            DB::transaction(function () use ($questionnaire, $question, $request) {
                $this->lockGuard->assertMatches($questionnaire, $this->ifMatch($request));
                $question->delete();
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
