<?php

namespace App\Http\Controllers\Api;

use App\Domains\Questionnaires\Exceptions\QuestionnaireLockConflictException;
use App\Domains\Questionnaires\Models\MatrixRow;
use App\Domains\Questionnaires\Models\Question;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Support\QuestionnaireLockGuard;
use App\Http\Controllers\Api\Concerns\HandlesQuestionnaireLock;
use App\Http\Controllers\Controller;
use App\Http\Resources\MatrixRowResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Baris matrix (FSD §7.3, tipe `matrix`) — kolom/skala matrix memakai
// question_options yang sama seperti single_choice (lihat QuestionOptionController).
class MatrixRowController extends Controller
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
            'sort_order' => ['nullable', 'integer'],
        ]);

        $row = DB::transaction(function () use ($questionnaire, $question, $data, $request) {
            $this->lockGuard->assertMatches($questionnaire, $this->ifMatch($request));

            $row = $question->matrixRows()->create([
                ...$data,
                'sort_order' => $data['sort_order'] ?? ($question->matrixRows()->max('sort_order') + 1),
            ]);

            $this->lockGuard->bump($questionnaire);

            return $row;
        });

        return (new MatrixRowResource($row))->response()->setStatusCode(201);
    }

    public function update(Request $request, MatrixRow $matrixRow): MatrixRowResource|JsonResponse
    {
        $questionnaire = $matrixRow->question->questionnaire;
        $this->abortIfNotEditable($questionnaire);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:100'],
            'label' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        try {
            DB::transaction(function () use ($questionnaire, $matrixRow, $data, $request) {
                $this->lockGuard->assertMatches($questionnaire, $this->ifMatch($request));
                $matrixRow->update($data);
                $this->lockGuard->bump($questionnaire);
            });
        } catch (QuestionnaireLockConflictException $e) {
            return $this->conflictResponse($e);
        }

        return new MatrixRowResource($matrixRow->fresh());
    }

    public function destroy(Request $request, MatrixRow $matrixRow): JsonResponse
    {
        $questionnaire = $matrixRow->question->questionnaire;
        $this->abortIfNotEditable($questionnaire);

        try {
            DB::transaction(function () use ($questionnaire, $matrixRow, $request) {
                $this->lockGuard->assertMatches($questionnaire, $this->ifMatch($request));
                $matrixRow->delete();
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
