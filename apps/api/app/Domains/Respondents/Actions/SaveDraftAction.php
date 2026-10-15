<?php

namespace App\Domains\Respondents\Actions;

use App\Domains\Respondents\Exceptions\AnswerValidationFailedException;
use App\Domains\Respondents\Models\ResponseSession;
use App\Domains\Respondents\Support\AnswerShapeValidator;
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
        private readonly AnswerShapeValidator $shapeValidator,
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
            // TTL rolling (slice 6) -- responden yang masih aktif mengisi
            // tidak boleh kena expire di tengah jalan.
            $session->expires_at = now()->addDays(ResponseSession::DRAFT_TTL_DAYS);
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
        $questionsByCode = $this->shapeValidator->questionsByCode($structure);

        $errors = [];

        foreach ($answers as $code => $value) {
            $question = $questionsByCode->get($code);

            if ($question === null) {
                $errors[] = ['code' => 'unknown_question', 'field' => $code, 'message' => "Pertanyaan '{$code}' tidak ditemukan di kuesioner ini."];

                continue;
            }

            $error = $this->shapeValidator->shapeError($question, $value);

            if ($error !== null) {
                $errors[] = ['code' => $error, 'field' => $code, 'message' => "Jawaban untuk '{$code}' tidak sesuai tipe pertanyaan."];
            }
        }

        if ($errors !== []) {
            throw new AnswerValidationFailedException($errors);
        }
    }
}
