<?php

namespace App\Http\Controllers\Api;

use App\Domains\Questionnaires\Actions\CreateQuestionnaireAction;
use App\Domains\Questionnaires\Actions\FinalizeQuestionnaireAction;
use App\Domains\Questionnaires\Actions\TransitionQuestionnaireStatusAction;
use App\Domains\Questionnaires\Actions\UpdateQuestionnaireAction;
use App\Domains\Questionnaires\Actions\ValidateQuestionnaireStructureAction;
use App\Domains\Questionnaires\Exceptions\QuestionnaireLockConflictException;
use App\Domains\Questionnaires\Exceptions\QuestionnaireValidationFailedException;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Http\Controllers\Api\Concerns\HandlesQuestionnaireLock;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionnaireResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

// FSD §17.3 — endpoint builder Kuesioner. Semua mutasi (store/update/finalize/
// transition) wajib header `If-Match` berisi lock_version terkini (FSD §7.7);
// mismatch -> 409 lewat QuestionnaireLockConflictException.
class QuestionnaireController extends Controller
{
    use HandlesQuestionnaireLock;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Questionnaire::query();

        if ($search = $request->string('search')->trim()->value()) {
            $needle = mb_strtolower($search);
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('lower(code) like ?', ["{$needle}%"])
                    ->orWhereRaw('lower(title) like ?', ["%{$needle}%"]);
            });
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        if ($category = $request->string('category')->value()) {
            $query->where('category', $category);
        }

        $perPage = min($request->integer('per_page', 15), 100);

        return QuestionnaireResource::collection($query->orderByDesc('updated_at')->paginate($perPage));
    }

    public function store(Request $request, CreateQuestionnaireAction $action): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'audience_type' => ['required', 'string', 'max:100'],
            'identity_mode' => ['required', 'string', 'max:100'],
            'template_id' => ['nullable', 'uuid'],
        ]);

        $questionnaire = $action->execute($data, Auth::user());

        return (new QuestionnaireResource($questionnaire))->response()->setStatusCode(201);
    }

    public function show(Questionnaire $questionnaire): QuestionnaireResource
    {
        $questionnaire->load(['sections.questions.options', 'sections.questions.matrixRows']);

        return new QuestionnaireResource($questionnaire);
    }

    public function update(Request $request, Questionnaire $questionnaire, UpdateQuestionnaireAction $action): QuestionnaireResource|JsonResponse
    {
        $this->abortIfNotEditable($questionnaire);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'audience_type' => ['sometimes', 'string', 'max:100'],
            'identity_mode' => ['sometimes', 'string', 'max:100'],
            'allow_draft_save' => ['sometimes', 'boolean'],
            'privacy_notice' => ['nullable', 'string'],
            'confirmation_text' => ['nullable', 'string'],
        ]);

        try {
            $questionnaire = $action->execute($questionnaire, $data, $this->ifMatch($request));
        } catch (QuestionnaireLockConflictException $e) {
            return $this->conflictResponse($e);
        }

        return new QuestionnaireResource($questionnaire);
    }

    public function destroy(Questionnaire $questionnaire): JsonResponse
    {
        $this->abortIfNotEditable($questionnaire);

        $questionnaire->delete();

        return response()->json(null, 204);
    }

    public function duplicate(Questionnaire $questionnaire, CreateQuestionnaireAction $action): JsonResponse
    {
        $questionnaire->loadMissing(['sections.questions.options', 'sections.questions.matrixRows']);

        $copy = $action->execute([
            'code' => $questionnaire->code.'-COPY-'.substr((string) str()->uuid(), 0, 4),
            'title' => $questionnaire->title.' (Salinan)',
            'description' => $questionnaire->description,
            'category' => $questionnaire->category,
            'audience_type' => $questionnaire->audience_type,
            'identity_mode' => $questionnaire->identity_mode,
            'template_id' => $questionnaire->template_id,
        ], Auth::user());

        foreach ($questionnaire->sections as $section) {
            $newSection = $copy->sections()->create([
                'code' => $section->code,
                'title' => $section->title,
                'description' => $section->description,
                'sort_order' => $section->sort_order,
            ]);

            foreach ($section->questions as $question) {
                $newQuestion = $newSection->questions()->create([
                    'questionnaire_id' => $copy->id,
                    'code' => $question->code,
                    'type' => $question->type,
                    'text' => $question->text,
                    'help_text' => $question->help_text,
                    'required' => $question->required,
                    'allow_not_applicable' => $question->allow_not_applicable,
                    'validation_json' => $question->validation_json,
                    'sort_order' => $question->sort_order,
                ]);

                foreach ($question->options as $option) {
                    $newQuestion->options()->create([
                        'code' => $option->code,
                        'label' => $option->label,
                        'numeric_score' => $option->numeric_score,
                        'is_positive' => $option->is_positive,
                        'is_not_applicable' => $option->is_not_applicable,
                        'sort_order' => $option->sort_order,
                    ]);
                }

                foreach ($question->matrixRows as $row) {
                    $newQuestion->matrixRows()->create([
                        'code' => $row->code,
                        'label' => $row->label,
                        'sort_order' => $row->sort_order,
                    ]);
                }
            }
        }

        return (new QuestionnaireResource($copy->fresh(['sections.questions.options', 'sections.questions.matrixRows'])))
            ->response()->setStatusCode(201);
    }

    public function validateStructure(Questionnaire $questionnaire, ValidateQuestionnaireStructureAction $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($questionnaire)]);
    }

    public function finalize(Request $request, Questionnaire $questionnaire, FinalizeQuestionnaireAction $action): QuestionnaireResource|JsonResponse
    {
        try {
            $questionnaire = $action->execute($questionnaire, $this->ifMatch($request), Auth::user());
        } catch (QuestionnaireLockConflictException $e) {
            return $this->conflictResponse($e);
        } catch (QuestionnaireValidationFailedException $e) {
            return response()->json([
                'error' => [
                    'code' => 'questionnaire_not_valid',
                    'message' => $e->getMessage(),
                    'fields' => $e->errors,
                ],
            ], 422);
        }

        return new QuestionnaireResource($questionnaire);
    }

    public function transition(Request $request, Questionnaire $questionnaire, TransitionQuestionnaireStatusAction $action): QuestionnaireResource|JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:draft,active,paused,closed'],
        ]);

        try {
            $questionnaire = $action->execute($questionnaire, $data['status'], $this->ifMatch($request), Auth::user());
        } catch (QuestionnaireLockConflictException $e) {
            return $this->conflictResponse($e);
        } catch (RuntimeException $e) {
            return response()->json(['error' => ['code' => 'invalid_transition', 'message' => $e->getMessage()]], 422);
        }

        return new QuestionnaireResource($questionnaire);
    }

    private function abortIfNotEditable(Questionnaire $questionnaire): void
    {
        abort_unless($questionnaire->isEditable(), 422, 'Kuesioner yang sudah difinalisasi tidak bisa diedit langsung.');
    }
}
