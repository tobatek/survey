<?php

namespace App\Domains\Respondents\Support;

use Illuminate\Support\Collection;

// Dipakai SaveDraftAction (autosave, slice 3) & SubmitResponseAction (submit
// final, slice 4) -- keduanya perlu memvalidasi bentuk nilai jawaban sesuai
// tipe question di snapshot (FSD §17.1 VALIDATION_FAILED). Submit menambah
// pengecekan wajib-isi (required) di atas ini, tapi bentuk nilainya sama.
class AnswerShapeValidator
{
    /**
     * @param  array<string, mixed>  $structure  snapshot structure_json
     * @return Collection<string, array<string, mixed>> question_code => question
     */
    public function questionsByCode(array $structure): Collection
    {
        return collect($structure['sections'] ?? [])
            ->flatMap(fn (array $section) => $section['questions'] ?? [])
            ->keyBy('code');
    }

    /**
     * @param  array<string, mixed>  $question
     */
    public function shapeError(array $question, mixed $value): ?string
    {
        $optionCodes = collect($question['options'] ?? [])->pluck('code')->all();
        $matrixRowCodes = collect($question['matrix_rows'] ?? [])->pluck('code')->all();

        return match ($question['type']) {
            'short_text', 'long_text' => is_string($value) ? null : 'invalid_text_answer',
            'number' => is_numeric($value) ? null : 'invalid_number_answer',
            'date' => is_string($value) && strtotime($value) !== false ? null : 'invalid_date_answer',
            'single_choice', 'dropdown', 'likert' => is_string($value) && in_array($value, $optionCodes, true) ? null : 'invalid_choice_answer',
            'multiple_choice' => is_array($value) && $value !== [] && collect($value)->every(fn ($code) => in_array($code, $optionCodes, true)) ? null : 'invalid_choice_answer',
            'matrix' => is_array($value) && collect($value)->every(
                fn ($optionCode, $rowCode) => in_array($rowCode, $matrixRowCodes, true) && in_array($optionCode, $optionCodes, true)
            ) ? null : 'invalid_matrix_answer',
            default => 'unknown_question_type',
        };
    }
}
