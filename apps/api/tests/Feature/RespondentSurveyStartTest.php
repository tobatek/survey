<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Respondents\Models\ResponseSession;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RespondentSurveyStartTest extends TestCase
{
    use RefreshDatabase;

    private function makeEligibility(string $campaignStatus = Campaign::STATUS_ACTIVE): array
    {
        $snapshot = QuestionnaireSnapshot::factory()->create();
        $campaign = Campaign::factory()->create([
            'status' => $campaignStatus,
            'questionnaire_id' => $snapshot->questionnaire_id,
            'snapshot_id' => $snapshot->id,
        ]);

        $student = Student::factory()->create();

        $member = AudienceMember::factory()->create([
            'campaign_id' => $campaign->id,
            'subject_type' => AudienceMember::SUBJECT_STUDENT,
            'subject_id' => $student->id,
            'attributes_snapshot_json' => ['program_study_code' => $student->program_study_code],
        ]);

        $eligibility = Eligibility::factory()->create([
            'campaign_id' => $campaign->id,
            'audience_member_id' => $member->id,
        ]);

        return [$campaign, $student, $eligibility];
    }

    #[Test]
    public function identified_user_can_start_and_resume_session(): void
    {
        [, $student, $eligibility] = $this->makeEligibility();

        $user = User::factory()->create(['email' => $student->institutional_email]);

        $first = $this->actingAs($user)->postJson("/api/my-surveys/{$eligibility->id}/start");
        $first->assertOk();
        $sessionId = $first->json('data.session_id');

        $second = $this->actingAs($user)->postJson("/api/my-surveys/{$eligibility->id}/start");
        $second->assertOk();

        $this->assertSame($sessionId, $second->json('data.session_id'));
        $this->assertSame(1, ResponseSession::query()->count());
        $this->assertSame(Eligibility::STATUS_STARTED, $eligibility->fresh()->status);
    }

    #[Test]
    public function unrelated_user_cannot_start_someone_elses_eligibility(): void
    {
        [, , $eligibility] = $this->makeEligibility();
        $other = User::factory()->create();

        $this->actingAs($other)->postJson("/api/my-surveys/{$eligibility->id}/start")->assertForbidden();
    }

    #[Test]
    public function start_is_rejected_when_campaign_is_paused(): void
    {
        [, $student, $eligibility] = $this->makeEligibility(Campaign::STATUS_PAUSED);
        $user = User::factory()->create(['email' => $student->institutional_email]);

        $response = $this->actingAs($user)->postJson("/api/my-surveys/{$eligibility->id}/start");

        $response->assertStatus(422)->assertJsonPath('error.code', 'SURVEY_PAUSED');
    }

    #[Test]
    public function anonymous_token_starts_and_resumes_the_same_session_without_eligibility_reference(): void
    {
        [, , $eligibility] = $this->makeEligibility();
        $rawToken = 'raw-token-example';
        $eligibility->update(['invitation_token_hash' => hash('sha256', $rawToken)]);

        $first = $this->postJson('/api/my-surveys/start-by-token', ['token' => $rawToken]);
        $first->assertOk();
        $sessionId = $first->json('data.session_id');

        $second = $this->postJson('/api/my-surveys/start-by-token', ['token' => $rawToken]);
        $second->assertOk();

        $this->assertSame($sessionId, $second->json('data.session_id'));

        $session = ResponseSession::query()->findOrFail($sessionId);
        $this->assertNull($session->eligibility_id);
        $this->assertNull($session->subject_type);
        $this->assertSame(ResponseSession::MODE_ANONYMOUS, $session->mode);
    }

    #[Test]
    public function invalid_token_returns_not_eligible(): void
    {
        $response = $this->postJson('/api/my-surveys/start-by-token', ['token' => 'bogus']);

        $response->assertStatus(404)->assertJsonPath('error.code', 'NOT_ELIGIBLE');
    }

    #[Test]
    public function my_surveys_lists_eligibilities_for_resolved_subject(): void
    {
        [, $student, $eligibility] = $this->makeEligibility();
        $user = User::factory()->create(['email' => $student->institutional_email]);

        $response = $this->actingAs($user)->getJson('/api/me/surveys');

        $response->assertOk();
        $response->assertJsonPath('data.0.eligibility_id', $eligibility->id);
        $response->assertJsonPath('data.0.respondent_status', 'not_started');
    }
}
