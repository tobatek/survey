<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Respondents\Models\Answer;
use App\Domains\Respondents\Models\AnswerChoice;
use App\Domains\Respondents\Models\ResponseSession;
use App\Domains\Respondents\Models\SurveyResponse;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResponseSubmitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * s1 (id1, tanpa branching) -> s2 (id2, branching q2 ke s3 id=3 atau s4
     * id=4). q1/q2/q3/q4 semua required=true, dipakai membuktikan bahwa
     * hanya pertanyaan di section yang REACHABLE yang diwajibkan saat submit.
     */
    private function makeSnapshot(): QuestionnaireSnapshot
    {
        return QuestionnaireSnapshot::factory()->create([
            'structure_json' => [
                'sections' => [
                    [
                        'id' => 1, 'code' => 's1', 'title' => 'Bagian 1', 'sort_order' => 1,
                        'questions' => [
                            [
                                'id' => 1, 'code' => 'q1', 'type' => 'single_choice', 'text' => 'Q1',
                                'required' => true, 'allow_not_applicable' => false, 'validation' => null,
                                'options' => [
                                    ['id' => 1, 'code' => 'only', 'label' => 'Satu-satunya', 'numeric_score' => 5, 'target_section_id' => null],
                                ],
                                'matrix_rows' => [],
                            ],
                        ],
                    ],
                    [
                        'id' => 2, 'code' => 's2', 'title' => 'Bagian 2', 'sort_order' => 2,
                        'questions' => [
                            [
                                'id' => 2, 'code' => 'q2', 'type' => 'single_choice', 'text' => 'Q2',
                                'required' => true, 'allow_not_applicable' => false, 'validation' => null,
                                'options' => [
                                    ['id' => 2, 'code' => 'to_s3', 'label' => 'Ke Bagian 3', 'numeric_score' => null, 'target_section_id' => 3],
                                    ['id' => 3, 'code' => 'to_s4', 'label' => 'Ke Bagian 4', 'numeric_score' => null, 'target_section_id' => 4],
                                ],
                                'matrix_rows' => [],
                            ],
                        ],
                    ],
                    [
                        'id' => 3, 'code' => 's3', 'title' => 'Bagian 3', 'sort_order' => 3,
                        'questions' => [
                            ['id' => 3, 'code' => 'q3', 'type' => 'short_text', 'text' => 'Q3', 'required' => true, 'allow_not_applicable' => false, 'validation' => null, 'options' => [], 'matrix_rows' => []],
                        ],
                    ],
                    [
                        'id' => 4, 'code' => 's4', 'title' => 'Bagian 4', 'sort_order' => 4,
                        'questions' => [
                            ['id' => 4, 'code' => 'q4', 'type' => 'short_text', 'text' => 'Q4', 'required' => true, 'allow_not_applicable' => false, 'validation' => null, 'options' => [], 'matrix_rows' => []],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $draftAnswers
     * @return array{0: ResponseSession, 1: User, 2: Eligibility}
     */
    private function makeIdentifiedSubmittableSession(array $draftAnswers): array
    {
        $snapshot = $this->makeSnapshot();
        $campaign = Campaign::factory()->create([
            'status' => Campaign::STATUS_ACTIVE,
            'questionnaire_id' => $snapshot->questionnaire_id,
            'snapshot_id' => $snapshot->id,
        ]);
        $student = Student::factory()->create();
        $member = AudienceMember::factory()->create([
            'campaign_id' => $campaign->id,
            'subject_type' => AudienceMember::SUBJECT_STUDENT,
            'subject_id' => $student->id,
        ]);
        $eligibility = Eligibility::factory()->create([
            'campaign_id' => $campaign->id,
            'audience_member_id' => $member->id,
            'status' => Eligibility::STATUS_STARTED,
        ]);

        $session = ResponseSession::create([
            'campaign_id' => $campaign->id,
            'snapshot_id' => $snapshot->id,
            'eligibility_id' => $eligibility->id,
            'mode' => ResponseSession::MODE_IDENTIFIED,
            'subject_type' => AudienceMember::SUBJECT_STUDENT,
            'subject_id' => $student->id,
            'segment_snapshot_json' => $member->attributes_snapshot_json,
            'status' => ResponseSession::STATUS_DRAFT,
            'draft_answers_json' => $draftAnswers,
            'started_at' => now()->subMinutes(5),
            'tenant_id' => $campaign->tenant_id,
        ]);

        $user = User::factory()->create(['email' => $student->institutional_email]);

        return [$session, $user, $eligibility];
    }

    /**
     * @param  array<string, mixed>  $draftAnswers
     * @return array{0: ResponseSession, 1: Eligibility}
     */
    private function makeAnonymousSubmittableSession(string $rawToken, array $draftAnswers): array
    {
        $snapshot = $this->makeSnapshot();
        $campaign = Campaign::factory()->create([
            'status' => Campaign::STATUS_ACTIVE,
            'questionnaire_id' => $snapshot->questionnaire_id,
            'snapshot_id' => $snapshot->id,
        ]);
        $student = Student::factory()->create();
        $member = AudienceMember::factory()->create([
            'campaign_id' => $campaign->id,
            'subject_type' => AudienceMember::SUBJECT_STUDENT,
            'subject_id' => $student->id,
        ]);
        $eligibility = Eligibility::factory()->create([
            'campaign_id' => $campaign->id,
            'audience_member_id' => $member->id,
            'status' => Eligibility::STATUS_STARTED,
            'invitation_token_hash' => hash('sha256', $rawToken),
        ]);

        $session = ResponseSession::create([
            'campaign_id' => $campaign->id,
            'snapshot_id' => $snapshot->id,
            'mode' => ResponseSession::MODE_ANONYMOUS,
            'anonymous_nonce_hash' => hash('sha256', $rawToken.'|response_session'),
            'segment_snapshot_json' => $member->attributes_snapshot_json,
            'status' => ResponseSession::STATUS_DRAFT,
            'draft_answers_json' => $draftAnswers,
            'started_at' => now()->subMinutes(5),
            'tenant_id' => $campaign->tenant_id,
        ]);

        return [$session, $eligibility];
    }

    #[Test]
    public function identified_submit_finalizes_answers_and_completes_eligibility(): void
    {
        [$session, $user, $eligibility] = $this->makeIdentifiedSubmittableSession([
            'q1' => 'only',
            'q2' => 'to_s4',
            'q4' => 'isian bagian 4',
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/responses/{$session->id}/submit",
            [],
            ['Idempotency-Key' => 'key-1'],
        );

        $response->assertOk();
        $this->assertNotNull($response->json('data.response_id'));

        $this->assertSame(1, SurveyResponse::query()->count());
        $this->assertSame(3, Answer::query()->count());
        $this->assertSame(2, AnswerChoice::query()->count());

        $this->assertSame(ResponseSession::STATUS_COMPLETED, $session->fresh()->status);
        $this->assertSame(Eligibility::STATUS_COMPLETED, $eligibility->fresh()->status);
        $this->assertNotNull($eligibility->fresh()->completion_receipt_hash);
    }

    #[Test]
    public function resubmitting_with_same_idempotency_key_replays_the_same_response(): void
    {
        [$session, $user] = $this->makeIdentifiedSubmittableSession([
            'q1' => 'only', 'q2' => 'to_s4', 'q4' => 'isian bagian 4',
        ]);

        $first = $this->actingAs($user)->postJson(
            "/api/responses/{$session->id}/submit",
            [],
            ['Idempotency-Key' => 'same-key'],
        )->assertOk();

        $second = $this->actingAs($user)->postJson(
            "/api/responses/{$session->id}/submit",
            [],
            ['Idempotency-Key' => 'same-key'],
        )->assertOk();

        $this->assertSame($first->json('data.response_id'), $second->json('data.response_id'));
        $this->assertSame(1, SurveyResponse::query()->count());
    }

    #[Test]
    public function resubmitting_with_a_different_idempotency_key_after_completion_is_rejected(): void
    {
        [$session, $user] = $this->makeIdentifiedSubmittableSession([
            'q1' => 'only', 'q2' => 'to_s4', 'q4' => 'isian bagian 4',
        ]);

        $this->actingAs($user)->postJson(
            "/api/responses/{$session->id}/submit",
            [],
            ['Idempotency-Key' => 'key-a'],
        )->assertOk();

        $second = $this->actingAs($user)->postJson(
            "/api/responses/{$session->id}/submit",
            [],
            ['Idempotency-Key' => 'key-b'],
        );

        $second->assertStatus(422)->assertJsonPath('error.code', 'ALREADY_COMPLETED');
        $this->assertSame(1, SurveyResponse::query()->count());
    }

    #[Test]
    public function submit_without_idempotency_key_header_is_rejected(): void
    {
        [$session, $user] = $this->makeIdentifiedSubmittableSession(['q1' => 'only', 'q2' => 'to_s3', 'q3' => 'x']);

        $response = $this->actingAs($user)->postJson("/api/responses/{$session->id}/submit");

        $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    #[Test]
    public function missing_required_answer_in_reachable_section_is_rejected(): void
    {
        [$session, $user] = $this->makeIdentifiedSubmittableSession([
            'q1' => 'only', 'q2' => 'to_s4',
            // q4 sengaja tidak diisi, padahal required & reachable.
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/responses/{$session->id}/submit",
            [],
            ['Idempotency-Key' => 'key-missing'],
        );

        $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $response->assertJsonPath('error.fields.0.field', 'q4');
        $this->assertSame(0, SurveyResponse::query()->count());
    }

    #[Test]
    public function required_question_in_unreachable_section_is_not_enforced(): void
    {
        // q2='to_s4' membuat s3 (dengan q3 required) tidak reachable --
        // q3 kosong TIDAK boleh menggagalkan submit.
        [$session, $user] = $this->makeIdentifiedSubmittableSession([
            'q1' => 'only', 'q2' => 'to_s4', 'q4' => 'isian bagian 4',
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/responses/{$session->id}/submit",
            [],
            ['Idempotency-Key' => 'key-unreachable'],
        );

        $response->assertOk();
    }

    #[Test]
    public function anonymous_submit_with_correct_token_succeeds_without_storing_identity(): void
    {
        $rawToken = 'anon-submit-token';
        [$session, $eligibility] = $this->makeAnonymousSubmittableSession($rawToken, [
            'q1' => 'only', 'q2' => 'to_s4', 'q4' => 'isian bagian 4',
        ]);

        $response = $this->postJson(
            "/api/responses/{$session->id}/submit",
            ['token' => $rawToken],
            ['Idempotency-Key' => 'anon-key'],
        );

        $response->assertOk();

        $surveyResponse = SurveyResponse::query()->findOrFail($response->json('data.response_id'));
        $this->assertNull($surveyResponse->subject_id);
        $this->assertNull($surveyResponse->subject_type);
        $this->assertSame(Eligibility::STATUS_COMPLETED, $eligibility->fresh()->status);
    }

    #[Test]
    public function anonymous_submit_with_wrong_token_is_rejected(): void
    {
        [$session] = $this->makeAnonymousSubmittableSession('correct-token', [
            'q1' => 'only', 'q2' => 'to_s4', 'q4' => 'isian bagian 4',
        ]);

        $response = $this->postJson(
            "/api/responses/{$session->id}/submit",
            ['token' => 'token-yang-salah'],
            ['Idempotency-Key' => 'anon-key-wrong'],
        );

        $response->assertStatus(422)->assertJsonPath('error.code', 'NOT_ELIGIBLE');
        $this->assertSame(0, SurveyResponse::query()->count());
    }

    #[Test]
    public function lock_backend_failure_is_reported_as_service_temporarily_unavailable(): void
    {
        [$session, $user] = $this->makeIdentifiedSubmittableSession([
            'q1' => 'only', 'q2' => 'to_s4', 'q4' => 'isian bagian 4',
        ]);

        Cache::shouldReceive('lock')->once()->andThrow(new \RuntimeException('cache backend unreachable'));

        $response = $this->actingAs($user)->postJson(
            "/api/responses/{$session->id}/submit",
            [],
            ['Idempotency-Key' => 'key-lock-down'],
        );

        $response->assertStatus(503)->assertJsonPath('error.code', 'SERVICE_TEMPORARILY_UNAVAILABLE');
        $this->assertSame(0, SurveyResponse::query()->count());
    }
}
