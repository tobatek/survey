<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Respondents\Models\ResponseSession;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResponseDraftTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Struktur 4 section: s1 (tanpa branching, id=1) -> s2 (id=2, branching
     * lewat q2 ke s3 id=3 atau s4 id=4) -> s3/s4 masing-masing 1 pertanyaan
     * short_text. Dipakai menguji reachability & pembuangan jawaban section
     * yang tidak lagi reachable.
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
                                    ['id' => 1, 'code' => 'only', 'label' => 'Satu-satunya', 'numeric_score' => null, 'target_section_id' => null],
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
                            ['id' => 3, 'code' => 'q3', 'type' => 'short_text', 'text' => 'Q3', 'required' => false, 'allow_not_applicable' => false, 'validation' => null, 'options' => [], 'matrix_rows' => []],
                        ],
                    ],
                    [
                        'id' => 4, 'code' => 's4', 'title' => 'Bagian 4', 'sort_order' => 4,
                        'questions' => [
                            ['id' => 4, 'code' => 'q4', 'type' => 'short_text', 'text' => 'Q4', 'required' => false, 'allow_not_applicable' => false, 'validation' => null, 'options' => [], 'matrix_rows' => []],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array{0: ResponseSession, 1: User}
     */
    private function makeIdentifiedSession(): array
    {
        $snapshot = $this->makeSnapshot();
        $student = Student::factory()->create();

        $session = ResponseSession::factory()->identified()->create([
            'snapshot_id' => $snapshot->id,
            'subject_type' => AudienceMember::SUBJECT_STUDENT,
            'subject_id' => $student->id,
        ]);

        $user = User::factory()->create(['email' => $student->institutional_email]);

        return [$session, $user];
    }

    #[Test]
    public function identified_owner_can_load_empty_draft(): void
    {
        [$session, $user] = $this->makeIdentifiedSession();

        $response = $this->actingAs($user)->getJson("/api/responses/{$session->id}");

        $response->assertOk();
        $response->assertJsonPath('data.lock_version', 0);
        $response->assertJsonPath('data.draft_answers', []);
        $response->assertJsonPath('data.reachable_section_ids', [1, 2]);
    }

    #[Test]
    public function saving_draft_with_correct_if_match_bumps_lock_version(): void
    {
        [$session, $user] = $this->makeIdentifiedSession();

        $response = $this->actingAs($user)->putJson(
            "/api/responses/{$session->id}/draft",
            ['answers' => ['q1' => 'only']],
            ['If-Match' => '0'],
        );

        $response->assertOk();
        $response->assertJsonPath('data.lock_version', 1);
        $this->assertNotNull($response->json('data.last_saved_at'));
        $this->assertSame(['q1' => 'only'], $session->fresh()->draft_answers_json);
    }

    #[Test]
    public function saving_draft_with_stale_if_match_is_rejected(): void
    {
        [$session, $user] = $this->makeIdentifiedSession();
        $session->update(['lock_version' => 5]);

        $response = $this->actingAs($user)->putJson(
            "/api/responses/{$session->id}/draft",
            ['answers' => ['q1' => 'only']],
            ['If-Match' => '0'],
        );

        $response->assertStatus(409)->assertJsonPath('error.code', 'STALE_DRAFT');
    }

    #[Test]
    public function saving_draft_without_if_match_header_is_rejected(): void
    {
        [$session, $user] = $this->makeIdentifiedSession();

        $response = $this->actingAs($user)->putJson(
            "/api/responses/{$session->id}/draft",
            ['answers' => ['q1' => 'only']],
        );

        $response->assertStatus(409)->assertJsonPath('error.code', 'STALE_DRAFT');
    }

    #[Test]
    public function unrelated_user_cannot_access_someone_elses_draft(): void
    {
        [$session] = $this->makeIdentifiedSession();
        $other = User::factory()->create();

        $this->actingAs($other)->getJson("/api/responses/{$session->id}")->assertForbidden();
        $this->actingAs($other)->putJson(
            "/api/responses/{$session->id}/draft",
            ['answers' => []],
            ['If-Match' => '0'],
        )->assertForbidden();
    }

    #[Test]
    public function anonymous_session_draft_is_accessible_without_login(): void
    {
        $snapshot = $this->makeSnapshot();
        $session = ResponseSession::factory()->create(['snapshot_id' => $snapshot->id]);

        $this->getJson("/api/responses/{$session->id}")->assertOk();

        $put = $this->putJson(
            "/api/responses/{$session->id}/draft",
            ['answers' => ['q1' => 'only']],
            ['If-Match' => '0'],
        );
        $put->assertOk()->assertJsonPath('data.lock_version', 1);

        $session->refresh();
        $this->assertNull($session->eligibility_id);
        $this->assertNull($session->subject_id);
    }

    #[Test]
    public function unreachable_section_answers_are_stripped_when_branching_choice_changes(): void
    {
        [$session, $user] = $this->makeIdentifiedSession();

        $this->actingAs($user)->putJson(
            "/api/responses/{$session->id}/draft",
            ['answers' => ['q1' => 'only', 'q2' => 'to_s3', 'q3' => 'jawaban s3']],
            ['If-Match' => '0'],
        )->assertOk();

        $this->assertSame(
            ['q1' => 'only', 'q2' => 'to_s3', 'q3' => 'jawaban s3'],
            $session->fresh()->draft_answers_json,
        );

        $this->actingAs($user)->putJson(
            "/api/responses/{$session->id}/draft",
            ['answers' => ['q1' => 'only', 'q2' => 'to_s4', 'q3' => 'jawaban s3']],
            ['If-Match' => '1'],
        )->assertOk();

        $draft = $session->fresh()->draft_answers_json;
        $this->assertArrayNotHasKey('q3', $draft);
        $this->assertSame('to_s4', $draft['q2']);
    }

    #[Test]
    public function completed_session_rejects_draft_access(): void
    {
        [$session, $user] = $this->makeIdentifiedSession();
        $session->update(['status' => ResponseSession::STATUS_COMPLETED]);

        $response = $this->actingAs($user)->getJson("/api/responses/{$session->id}");

        $response->assertStatus(422)->assertJsonPath('error.code', 'ALREADY_COMPLETED');
    }

    #[Test]
    public function expired_session_rejects_draft_access(): void
    {
        [$session, $user] = $this->makeIdentifiedSession();
        $session->update(['expires_at' => now()->subMinute()]);

        $response = $this->actingAs($user)->getJson("/api/responses/{$session->id}");

        $response->assertStatus(422)->assertJsonPath('error.code', 'SESSION_EXPIRED');
    }

    #[Test]
    public function invalid_answer_shape_is_rejected(): void
    {
        [$session, $user] = $this->makeIdentifiedSession();

        $response = $this->actingAs($user)->putJson(
            "/api/responses/{$session->id}/draft",
            ['answers' => ['q1' => 'opsi_tidak_ada']],
            ['If-Match' => '0'],
        );

        $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }
}
