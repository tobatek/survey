<?php

namespace App\Domains\Questionnaires\Actions;

use App\Domains\Questionnaires\Models\Question;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Support\BranchGraphValidator;

// FSD §7.5 — dipakai baik oleh endpoint `validate` (laporan tanpa efek
// samping) maupun oleh FinalizeQuestionnaireAction (yang menolak finalize
// jika ada error). Warning tidak menghalangi finalisasi.
class ValidateQuestionnaireStructureAction
{
    public function __construct(private readonly BranchGraphValidator $branchGraphValidator) {}

    /**
     * @return array{errors: array<int, array{code: string, message: string}>, warnings: array<int, array{code: string, message: string}>}
     */
    public function execute(Questionnaire $questionnaire): array
    {
        $questionnaire->loadMissing(['sections', 'questions.options', 'questions.matrixRows']);

        $errors = [];
        $warnings = [];

        if (trim($questionnaire->title) === '') {
            $errors[] = ['code' => 'metadata_title_empty', 'message' => 'Judul kuesioner belum diisi.'];
        }

        if ($questionnaire->sections->isEmpty()) {
            $errors[] = ['code' => 'no_sections', 'message' => 'Kuesioner belum memiliki section.'];
        }

        if ($questionnaire->questions->isEmpty()) {
            $errors[] = ['code' => 'no_questions', 'message' => 'Kuesioner belum memiliki pertanyaan.'];
        }

        $duplicateSectionCodes = $questionnaire->sections
            ->groupBy('code')
            ->filter(fn ($group) => $group->count() > 1)
            ->keys();

        foreach ($duplicateSectionCodes as $code) {
            $errors[] = ['code' => 'duplicate_section_code', 'message' => "Kode section '{$code}' duplikat."];
        }

        $duplicateQuestionCodes = $questionnaire->questions
            ->groupBy('code')
            ->filter(fn ($group) => $group->count() > 1)
            ->keys();

        foreach ($duplicateQuestionCodes as $code) {
            $errors[] = ['code' => 'duplicate_question_code', 'message' => "Kode pertanyaan '{$code}' duplikat."];
        }

        foreach ($questionnaire->questions as $question) {
            $errors = [...$errors, ...$this->validateQuestion($question)];
        }

        $errors = [...$errors, ...$this->branchGraphValidator->validate($questionnaire)];

        $longQuestions = $questionnaire->questions->filter(fn ($q) => mb_strlen($q->text) > 200);

        foreach ($longQuestions as $question) {
            $warnings[] = [
                'code' => 'question_text_too_long',
                'message' => "Teks pertanyaan '{$question->code}' cukup panjang, pertimbangkan diringkas.",
            ];
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @return array<int, array{code: string, message: string}>
     */
    private function validateQuestion(Question $question): array
    {
        $errors = [];

        if (! in_array($question->type, Question::TYPES, true)) {
            $errors[] = [
                'code' => 'invalid_question_type',
                'message' => "Tipe pertanyaan '{$question->type}' pada '{$question->code}' tidak dikenal.",
            ];

            return $errors;
        }

        $needsOptions = in_array($question->type, [...Question::CHOICE_TYPES, 'multiple_choice'], true);

        if ($needsOptions && $question->options->isEmpty()) {
            $errors[] = [
                'code' => 'question_has_no_options',
                'message' => "Pertanyaan '{$question->code}' butuh minimal satu opsi.",
            ];
        }

        if ($needsOptions) {
            $duplicateOptionCodes = $question->options
                ->groupBy('code')
                ->filter(fn ($group) => $group->count() > 1)
                ->keys();

            foreach ($duplicateOptionCodes as $code) {
                $errors[] = [
                    'code' => 'duplicate_option_code',
                    'message' => "Kode opsi '{$code}' duplikat pada pertanyaan '{$question->code}'.",
                ];
            }

            foreach ($question->options as $option) {
                if (trim($option->label) === '') {
                    $errors[] = [
                        'code' => 'option_label_empty',
                        'message' => "Ada opsi kosong pada pertanyaan '{$question->code}'.",
                    ];
                }
            }
        }

        if ($question->type === 'matrix' && $question->matrixRows->isEmpty()) {
            $errors[] = [
                'code' => 'matrix_has_no_rows',
                'message' => "Pertanyaan matrix '{$question->code}' butuh minimal satu baris.",
            ];
        }

        if ($question->type === 'matrix' && $question->options->isEmpty()) {
            $errors[] = [
                'code' => 'matrix_has_no_scale',
                'message' => "Pertanyaan matrix '{$question->code}' butuh skala/opsi jawaban.",
            ];
        }

        return $errors;
    }
}
