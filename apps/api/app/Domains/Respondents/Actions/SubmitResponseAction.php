<?php

namespace App\Domains\Respondents\Actions;

use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Respondents\Exceptions\AnswerValidationFailedException;
use App\Domains\Respondents\Exceptions\SurveyAccessException;
use App\Domains\Respondents\Models\Answer;
use App\Domains\Respondents\Models\AnswerChoice;
use App\Domains\Respondents\Models\IdempotencyKey;
use App\Domains\Respondents\Models\ResponseSession;
use App\Domains\Respondents\Models\SurveyResponse;
use App\Domains\Respondents\Support\AnswerShapeValidator;
use App\Domains\Respondents\Support\AssertResponseSessionUsable;
use App\Domains\Respondents\Support\DraftReachabilityCalculator;
use App\Domains\Respondents\Support\ResponseSessionLockGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// FSD §11.4/§17.2/§20 -- POST /api/responses/{session}/submit. Bagian paling
// kritis Fase 4: memfinalisasi draft_answers_json (slice 3) jadi
// responses/answers/answer_choices yang immutable. Tiga lapis pertahanan
// race condition sesuai §20: Redis lock per-session (pola sama
// ActivateCampaignAction), conditional update status (pola sama
// TransitionCampaignStatusAction), dan unique constraint
// responses.submission_nonce_hash sebagai jaring pengaman terakhir.
class SubmitResponseAction
{
    public function __construct(
        private readonly AssertResponseSessionUsable $assertUsable,
        private readonly ResponseSessionLockGuard $lockGuard,
        private readonly DraftReachabilityCalculator $reachability,
        private readonly AnswerShapeValidator $shapeValidator,
    ) {}

    public function execute(ResponseSession $session, string $idempotencyKeyRaw, ?string $anonymousToken): SurveyResponse
    {
        $scopeHash = hash('sha256', "response_session:{$session->id}");
        $keyHash = hash('sha256', $idempotencyKeyRaw);

        $replay = $this->findCompletedReplay($scopeHash, $keyHash);

        if ($replay !== null) {
            return $replay;
        }

        $lock = Cache::lock("response-session:{$session->id}", 30);

        if (! $lock->block(5)) {
            throw new SurveyAccessException('SUBMISSION_IN_PROGRESS', 'Pengiriman jawaban lain untuk sesi ini sedang diproses. Coba lagi sesaat lagi.');
        }

        try {
            return DB::transaction(function () use ($session, $scopeHash, $keyHash, $anonymousToken) {
                // Tutup celah race: request lain dengan Idempotency-Key yang
                // sama mungkin baru saja commit persis sebelum kita dapat lock.
                $replay = $this->findCompletedReplay($scopeHash, $keyHash);

                if ($replay !== null) {
                    return $replay;
                }

                $session = $session->fresh();
                $this->assertUsable->execute($session);
                $this->assertCampaignOpen($session);

                $eligibility = $this->resolveEligibility($session, $anonymousToken);

                $affected = ResponseSession::query()
                    ->where('id', $session->id)
                    ->whereIn('status', [ResponseSession::STATUS_DRAFT, ResponseSession::STATUS_SUBMITTING])
                    ->update(['status' => ResponseSession::STATUS_SUBMITTING]);

                if ($affected === 0) {
                    $this->assertUsable->execute($session->fresh());

                    throw new SurveyAccessException('ALREADY_COMPLETED', 'Sesi ini sudah tidak bisa disubmit.');
                }

                $session->loadMissing('snapshot');
                $structure = $session->snapshot->structure_json;
                $draftAnswers = $session->draft_answers_json ?? [];
                $reachableSectionIds = $this->reachability->reachableSectionIds($structure, $draftAnswers);

                $this->validateRequiredAndShape($structure, $reachableSectionIds, $draftAnswers);

                $response = $this->createResponse($session);

                if ($response->wasRecentlyCreated) {
                    $this->createAnswers($response, $structure, $draftAnswers);
                }

                $session->status = ResponseSession::STATUS_COMPLETED;
                $session->completed_at = now();
                $session->save();
                $this->lockGuard->bump($session);

                $this->completeEligibility($eligibility, $response);

                IdempotencyKey::query()->updateOrCreate(
                    ['scope_hash' => $scopeHash, 'key_hash' => $keyHash],
                    [
                        'request_hash' => hash('sha256', 'submit:'.$session->id),
                        'status' => IdempotencyKey::STATUS_COMPLETED,
                        'response_reference' => $response->id,
                        'expires_at' => now()->addDay(),
                        'created_at' => now(),
                    ],
                );

                return $response;
            });
        } finally {
            $lock->release();
        }
    }

    private function findCompletedReplay(string $scopeHash, string $keyHash): ?SurveyResponse
    {
        $key = IdempotencyKey::query()
            ->where('scope_hash', $scopeHash)
            ->where('key_hash', $keyHash)
            ->first();

        if ($key?->status === IdempotencyKey::STATUS_COMPLETED && $key->response_reference !== null) {
            return SurveyResponse::query()->findOrFail($key->response_reference);
        }

        return null;
    }

    private function assertCampaignOpen(ResponseSession $session): void
    {
        $session->loadMissing('campaign');

        match ($session->campaign->status) {
            Campaign::STATUS_ACTIVE => null,
            Campaign::STATUS_PAUSED => throw new SurveyAccessException('SURVEY_PAUSED', 'Survei ini sedang dijeda oleh pengelola.'),
            Campaign::STATUS_CLOSED => throw new SurveyAccessException('SURVEY_CLOSED', 'Survei ini sudah ditutup.'),
            default => throw new SurveyAccessException('SURVEY_NOT_STARTED', 'Survei ini belum dimulai.'),
        };
    }

    // Identified: eligibility sudah diketahui dari session->eligibility_id.
    // Anonymous: session TIDAK PERNAH menyimpan eligibility_id (kontrak
    // privasi slice 1) -- satu-satunya cara menemukan & menyelesaikan
    // eligibility yang tepat adalah client mengirim ulang token mentah,
    // dicocokkan ke anonymous_nonce_hash sesi (bukti kepemilikan) lalu ke
    // invitation_token_hash eligibility (pola sama StartResponseSessionAction).
    private function resolveEligibility(ResponseSession $session, ?string $anonymousToken): ?Eligibility
    {
        if ($session->mode === ResponseSession::MODE_IDENTIFIED) {
            return $session->eligibility_id !== null
                ? Eligibility::query()->find($session->eligibility_id)
                : null;
        }

        if ($anonymousToken === null || $anonymousToken === '') {
            throw new SurveyAccessException('NOT_ELIGIBLE', 'Token tidak ditemukan untuk sesi ini.');
        }

        $expectedNonce = hash('sha256', $anonymousToken.'|response_session');

        if (! hash_equals($session->anonymous_nonce_hash ?? '', $expectedNonce)) {
            throw new SurveyAccessException('NOT_ELIGIBLE', 'Token tidak cocok dengan sesi ini.');
        }

        return Eligibility::query()->where('invitation_token_hash', hash('sha256', $anonymousToken))->first();
    }

    /**
     * @param  array<string, mixed>  $structure
     * @param  array<int, int>  $reachableSectionIds
     * @param  array<string, mixed>  $draftAnswers
     */
    private function validateRequiredAndShape(array $structure, array $reachableSectionIds, array $draftAnswers): void
    {
        $reachableQuestions = collect($structure['sections'] ?? [])
            ->filter(fn (array $section) => in_array($section['id'], $reachableSectionIds, true))
            ->flatMap(fn (array $section) => $section['questions'] ?? []);

        $errors = [];

        foreach ($reachableQuestions as $question) {
            $value = $draftAnswers[$question['code']] ?? null;

            if ($value === null) {
                if ($question['required']) {
                    $errors[] = ['code' => 'required_answer_missing', 'field' => $question['code'], 'message' => "Pertanyaan '{$question['code']}' wajib diisi."];
                }

                continue;
            }

            $error = $this->shapeValidator->shapeError($question, $value);

            if ($error !== null) {
                $errors[] = ['code' => $error, 'field' => $question['code'], 'message' => "Jawaban untuk '{$question['code']}' tidak sesuai tipe pertanyaan."];
            }
        }

        if ($errors !== []) {
            throw new AnswerValidationFailedException($errors);
        }
    }

    private function createResponse(ResponseSession $session): SurveyResponse
    {
        $submissionNonceHash = hash('sha256', "{$session->id}|submission");

        try {
            return SurveyResponse::create([
                'campaign_id' => $session->campaign_id,
                'snapshot_id' => $session->snapshot_id,
                'response_session_id' => $session->id,
                'mode' => $session->mode,
                'subject_type' => $session->mode === ResponseSession::MODE_IDENTIFIED ? $session->subject_type : null,
                'subject_id' => $session->mode === ResponseSession::MODE_IDENTIFIED ? $session->subject_id : null,
                'segment_snapshot_json' => $session->segment_snapshot_json,
                'submission_nonce_hash' => $submissionNonceHash,
                'submitted_at' => now(),
                'response_duration_seconds' => $session->started_at !== null ? (int) round($session->started_at->diffInSeconds(now())) : null,
                'schema_version' => 1,
                'tenant_id' => $session->tenant_id,
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            return SurveyResponse::query()->where('submission_nonce_hash', $submissionNonceHash)->firstOrFail();
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;

        return $sqlState === '23505' || $driverCode === 19 || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }

    /**
     * @param  array<string, mixed>  $structure
     * @param  array<string, mixed>  $draftAnswers
     */
    private function createAnswers(SurveyResponse $response, array $structure, array $draftAnswers): void
    {
        $questionsByCode = $this->shapeValidator->questionsByCode($structure);

        foreach ($draftAnswers as $code => $value) {
            $question = $questionsByCode->get($code);

            if ($question === null) {
                continue;
            }

            $optionsByCode = collect($question['options'] ?? [])->keyBy('code');

            match ($question['type']) {
                'short_text', 'long_text' => $this->createSimpleAnswer($response, $question, ['text_value' => $value]),
                'number' => $this->createSimpleAnswer($response, $question, ['numeric_value' => $value]),
                'date' => $this->createSimpleAnswer($response, $question, ['date_value' => $value]),
                'single_choice', 'dropdown', 'likert' => $this->createChoiceAnswer($response, $question, null, $value, $optionsByCode),
                'multiple_choice' => $this->createMultipleChoiceAnswer($response, $question, $value, $optionsByCode),
                'matrix' => $this->createMatrixAnswer($response, $question, $value, $optionsByCode),
                default => null,
            };
        }
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $valueColumn
     */
    private function createSimpleAnswer(SurveyResponse $response, array $question, array $valueColumn): void
    {
        Answer::create(array_merge([
            'response_id' => $response->id,
            'snapshot_question_code' => $question['code'],
            'matrix_row_code' => null,
            'value_type' => $question['type'],
            'is_not_applicable' => false,
            'created_at' => now(),
        ], $valueColumn));
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  Collection<string, array<string, mixed>>  $optionsByCode
     */
    private function createChoiceAnswer(SurveyResponse $response, array $question, ?string $matrixRowCode, string $optionCode, Collection $optionsByCode): void
    {
        $answer = Answer::create([
            'response_id' => $response->id,
            'snapshot_question_code' => $question['code'],
            'matrix_row_code' => $matrixRowCode,
            'value_type' => $question['type'],
            'is_not_applicable' => false,
            'created_at' => now(),
        ]);

        AnswerChoice::create([
            'answer_id' => $answer->id,
            'option_code' => $optionCode,
            'numeric_score' => $optionsByCode->get($optionCode)['numeric_score'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<int, string>  $optionCodes
     * @param  Collection<string, array<string, mixed>>  $optionsByCode
     */
    private function createMultipleChoiceAnswer(SurveyResponse $response, array $question, array $optionCodes, Collection $optionsByCode): void
    {
        $answer = Answer::create([
            'response_id' => $response->id,
            'snapshot_question_code' => $question['code'],
            'matrix_row_code' => null,
            'value_type' => $question['type'],
            'is_not_applicable' => false,
            'created_at' => now(),
        ]);

        foreach ($optionCodes as $optionCode) {
            AnswerChoice::create([
                'answer_id' => $answer->id,
                'option_code' => $optionCode,
                'numeric_score' => $optionsByCode->get($optionCode)['numeric_score'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, string>  $rowAnswers  matrix_row_code => option_code
     * @param  Collection<string, array<string, mixed>>  $optionsByCode
     */
    private function createMatrixAnswer(SurveyResponse $response, array $question, array $rowAnswers, Collection $optionsByCode): void
    {
        foreach ($rowAnswers as $rowCode => $optionCode) {
            $this->createChoiceAnswer($response, $question, $rowCode, $optionCode, $optionsByCode);
        }
    }

    private function completeEligibility(?Eligibility $eligibility, SurveyResponse $response): void
    {
        if ($eligibility === null || $eligibility->status === Eligibility::STATUS_COMPLETED) {
            return;
        }

        $eligibility->update([
            'status' => Eligibility::STATUS_COMPLETED,
            'completed_at' => now(),
            'completion_receipt_hash' => hash('sha256', $response->id),
        ]);
    }
}
