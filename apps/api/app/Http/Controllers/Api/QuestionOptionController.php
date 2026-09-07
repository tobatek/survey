<?php

namespace App\Http\Controllers\Api;

use App\Domains\Questionnaires\Exceptions\QuestionnaireLockConflictException;
use App\Domains\Questionnaires\Models\Question;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\QuestionOption;
use App\Domains\Questionnaires\Support\QuestionnaireLockGuard;
use App\Http\Controllers\Api\Concerns\HandlesQuestionnaireLock;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionOptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionOptionController extends Controller
{
    use HandlesQuestionnaireLock;

    public function __construct(private readonly QuestionnaireLockGuard $lockGuard) {}

    public function store(Request $request, Question $question): JsonResponse
    {
        $questionnaire = $question->questionnaire;
        $this->abortIfNotEditable($questionnaire);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'label' => ['required', 'string', 'max:255'],
            'numeric_score' => ['nullable', 'numeric'],
            'is_positive' => ['sometimes', 'boolean'],
            'is_not_applicable' => ['sometimes', 'boolean'],
            'target_section_id' => ['nullable', 'uuid', 'exists:questionnaire_sections,id'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $option = DB::transaction(function () use ($questionnaire, $question, $data, $request) {
            $this->lockGuard->assertMatches($questionnaire, $this->ifMatch($request));

            $option = $question->options()->create([
                ...$data,
                'sort_order' => $data['sort_order'] ?? ($question->options()->max('sort_order') + 1),
            ]);

            $this->lockGuard->bump($questionnaire);

            return $option->fresh();
        });

        return (new QuestionOptionResource($option))->response()->setStatusCode(201);
    }

    public function update(Request $request, QuestionOption $option): QuestionOptionResource|JsonResponse
    {
        $questionnaire = $option->question->questionnaire;
        $this->abortIfNotEditable($questionnaire);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:100'],
            'label' => ['sometimes', 'string', 'max:255'],
            'numeric_score' => ['nullable', 'numeric'],
            'is_positive' => ['sometimes', 'boolean'],
            'is_not_applicable' => ['sometimes', 'boolean'],
            'target_section_id' => ['nullable', 'uuid', 'exists:questionnaire_sections,id'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        try {
            DB::transaction(function () use ($questionnaire, $option, $data, $request) {
                $this->lockGuard->assertMatches($questionnaire, $this->ifMatch($request));
                $option->update($data);
                $this->lockGuard->bump($questionnaire);
            });
        } catch (QuestionnaireLockConflictException $e) {
            return $this->conflictResponse($e);
        }

        return new QuestionOptionResource($option->fresh());
    }

    public function destroy(Request $request, QuestionOption $option): JsonResponse
    {
        $questionnaire = $option->question->questionnaire;
        $this->abortIfNotEditable($questionnaire);

        try {
            DB::transaction(function () use ($questionnaire, $option, $request) {
                $this->lockGuard->assertMatches($questionnaire, $this->ifMatch($request));
                $option->delete();
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
