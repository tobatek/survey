<?php

namespace App\Domains\Questionnaires\Actions;

use App\Domains\Audit\Actions\RecordAuditEventAction;
use App\Domains\Questionnaires\Exceptions\QuestionnaireValidationFailedException;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Questionnaires\Support\QuestionnaireLockGuard;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// FSD §7.6 — draft->ready: validasi (§7.5) wajib lolos (tanpa error, warning
// boleh ada), lalu struktur dibekukan jadi snapshot immutable + checksum.
// Setelah ready, questionnaire tidak lagi bisa diedit (lihat Questionnaire::isEditable()
// dan pengecekan status di controller CRUD section/question).
class FinalizeQuestionnaireAction
{
    public function __construct(
        private readonly ValidateQuestionnaireStructureAction $validator,
        private readonly QuestionnaireLockGuard $lockGuard,
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function execute(Questionnaire $questionnaire, ?int $ifMatch, User $actor): Questionnaire
    {
        return DB::transaction(function () use ($questionnaire, $ifMatch, $actor) {
            $this->lockGuard->assertMatches($questionnaire, $ifMatch);

            $result = $this->validator->execute($questionnaire);

            if ($result['errors'] !== []) {
                throw new QuestionnaireValidationFailedException($result['errors']);
            }

            $structure = $this->buildStructureJson($questionnaire);
            $checksum = hash('sha256', json_encode($structure, JSON_THROW_ON_ERROR));

            QuestionnaireSnapshot::query()->create([
                'questionnaire_id' => $questionnaire->id,
                'version' => $questionnaire->structure_version,
                'structure_json' => $structure,
                'checksum' => $checksum,
                'created_by' => $actor->id,
                'created_at' => now(),
            ]);

            $questionnaire->status = Questionnaire::STATUS_READY;
            $questionnaire->snapshot_checksum = $checksum;
            $questionnaire->finalized_by = $actor->id;
            $questionnaire->finalized_at = now();
            $questionnaire->save();
            $this->lockGuard->bump($questionnaire);

            $this->recordAuditEvent->execute(
                action: 'questionnaire.finalize',
                result: 'success',
                actor: $actor,
                resourceType: 'questionnaire',
                resourceId: $questionnaire->id,
                context: ['checksum' => $checksum],
            );

            return $questionnaire->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStructureJson(Questionnaire $questionnaire): array
    {
        $questionnaire->loadMissing(['sections.questions.options', 'sections.questions.matrixRows']);

        return [
            'questionnaire_id' => $questionnaire->id,
            'code' => $questionnaire->code,
            'title' => $questionnaire->title,
            'sections' => $questionnaire->sections->map(fn ($section) => [
                'id' => $section->id,
                'code' => $section->code,
                'title' => $section->title,
                'sort_order' => $section->sort_order,
                'questions' => $section->questions->map(fn ($question) => [
                    'id' => $question->id,
                    'code' => $question->code,
                    'type' => $question->type,
                    'text' => $question->text,
                    'required' => $question->required,
                    'allow_not_applicable' => $question->allow_not_applicable,
                    'validation' => $question->validation_json,
                    'options' => $question->options->map(fn ($option) => [
                        'id' => $option->id,
                        'code' => $option->code,
                        'label' => $option->label,
                        'numeric_score' => $option->numeric_score,
                        'target_section_id' => $option->target_section_id,
                    ])->all(),
                    'matrix_rows' => $question->matrixRows->map(fn ($row) => [
                        'id' => $row->id,
                        'code' => $row->code,
                        'label' => $row->label,
                    ])->all(),
                ])->all(),
            ])->all(),
        ];
    }
}
