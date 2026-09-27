<?php

namespace App\Domains\Respondents\Actions;

use App\Domains\Respondents\Exceptions\DraftValidationFailedException;
use App\Domains\Respondents\Models\ResponseSession;
use App\Domains\Respondents\Support\AssertResponseSessionUsable;
use App\Domains\Respondents\Support\DraftReachabilityCalculator;
use App\Domains\Respondents\Support\ResponseSessionLockGuard;
use Illuminate\Support\Facades\DB;

// FSD §11.3/§17.2 -- PUT /api/responses/{session}/draft: autosave. Client
// selalu mengirim seluruh state draft yang sedang dia lihat (full replace,
// bukan merge) supaya server tetap jadi sumber kebenaran tunggal (FSD:
// "server tetap menjadi sumber validasi"). Jawaban pada section yang sudah
// tidak reachable (karena user mengganti jawaban branching) dibuang otomatis
// sebelum disimpan.
class SaveDraftAction
{
    public function __construct(
        private readonly AssertResponseSessionUsable $assertUsable,
        private readonly ResponseSessionLockGuard $lockGuard,
        private readonly DraftReachabilityCalculator $reachability,
    ) {}

    /**
     * @param  array<string, mixed>  $answers  question_code => value
     */
    public function execute(ResponseSession $session, array $answers, ?int $ifMatch): ResponseSession
    {
        return DB::transaction(function () use ($session, $answers, $ifMatch) {
            $this->assertUsable->execute($session);
            $this->lockGuard->assertMatches($session, $ifMatch);

            $session->loadMissing('snapshot');
            $structure = $session->snapshot->structure_json;

            // null berarti "belum dijawab" -- draft hanya menyimpan jawaban
            // yang benar-benar terisi.
            $answers = array_filter($answers, fn ($value) => $value !== null);

            $this->validateAnswers($structure, $answers);

            $session->draft_answers_json = $this->reachability->stripUnreachableAnswers($structure, $answers);
            $session->last_saved_at = now();
            $session->save();
            $this->lockGuard->bump($session);

            return $session->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $structure
     * @param  array<string, mixed>  $answers
     */
    private function validateAnswers(array $structure, array $answers): void
    {
        $questionsByCode = collect($structure['sections'] ?? [])
            ->flatMap(fn (array $section) => $section['questions'] ?? [])
            ->keyBy('code');

        $errors = [];

        foreach ($answers as $code => $value) {
            $question = $questionsByCode->get($code);

            if ($question === null) {
                $errors[] = ['code' => 'unknown_question', 'field' => $code, 'message' => "Pertanyaan '{$code}' tidak ditemukan di kuesioner ini."];

                continue;
            }

            $error = $this->validateAnswerShape($question, $value);

            if ($error !== null) {
                $errors[] = ['code' => $error, 'field' => $code, 'message' => "Jawaban untuk '{$code}' tidak sesuai tipe pertanyaan."];
            }
        }

        if ($errors !== []) {
            throw new DraftValidationFailedException($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function validateAnswerShape(array $question, mixed $value): ?string
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
