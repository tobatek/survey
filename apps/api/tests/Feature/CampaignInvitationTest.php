<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Actions\ActivateCampaignAction;
use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\DeliveryAttempt;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Students\Models\Student;
use App\Jobs\SendCampaignInvitationsJob;
use App\Notifications\CampaignInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function makeEligibility(Campaign $campaign, ?string $email = 'mhs@example.ac.id'): Eligibility
    {
        $student = Student::factory()->create(['tenant_id' => 'default', 'institutional_email' => $email]);

        $member = AudienceMember::factory()->create([
            'campaign_id' => $campaign->id,
            'subject_type' => AudienceMember::SUBJECT_STUDENT,
            'subject_id' => $student->id,
        ]);

        return Eligibility::factory()->create([
            'campaign_id' => $campaign->id,
            'audience_member_id' => $member->id,
            'status' => Eligibility::STATUS_ELIGIBLE,
        ]);
    }

    #[Test]
    public function job_sends_invitation_and_records_a_successful_delivery_attempt(): void
    {
        Notification::fake();

        $questionnaire = Questionnaire::factory()->create(['title' => 'Survei Kepuasan']);
        $campaign = Campaign::factory()->create(['questionnaire_id' => $questionnaire->id]);
        $eligibility = $this->makeEligibility($campaign);

        (new SendCampaignInvitationsJob($campaign))->handle();

        Notification::assertSentOnDemand(CampaignInvitationNotification::class);

        $eligibility->refresh();
        $this->assertSame(Eligibility::STATUS_INVITED, $eligibility->status);
        $this->assertNotNull($eligibility->invitation_token_hash);

        $this->assertDatabaseHas('delivery_attempts', [
            'eligibility_id' => $eligibility->id,
            'status' => DeliveryAttempt::STATUS_SENT,
            'attempt_number' => 1,
        ]);
    }

    #[Test]
    public function job_records_a_failed_delivery_attempt_when_subject_has_no_email(): void
    {
        Notification::fake();

        $campaign = Campaign::factory()->create();
        $eligibility = $this->makeEligibility($campaign, email: null);

        (new SendCampaignInvitationsJob($campaign))->handle();

        Notification::assertNothingSent();

        $eligibility->refresh();
        $this->assertSame(Eligibility::STATUS_ELIGIBLE, $eligibility->status);

        $this->assertDatabaseHas('delivery_attempts', [
            'eligibility_id' => $eligibility->id,
            'status' => DeliveryAttempt::STATUS_FAILED,
            'error_code' => 'no_email',
        ]);
    }

    #[Test]
    public function activating_a_campaign_automatically_queues_the_invitation_job(): void
    {
        Queue::fake();

        // Bug ditemukan 2026-10-01 (uji end-to-end manual) -- questionnaire
        // WAJIB punya snapshot_checksum yang cocok dengan QuestionnaireSnapshot
        // sungguhan, kalau tidak ActivateCampaignAction menolak aktivasi
        // (lihat komentar di ActivateCampaignAction soal snapshot_id).
        $snapshot = QuestionnaireSnapshot::factory()->create();
        $questionnaire = $snapshot->questionnaire()->first();
        $questionnaire->update(['status' => Questionnaire::STATUS_READY, 'snapshot_checksum' => $snapshot->checksum]);
        Student::factory()->create(['tenant_id' => 'default', 'program_study_code' => 'TI']);

        $campaign = Campaign::factory()->create([
            'questionnaire_id' => $questionnaire->id,
            'target_rule_json' => ['program_study_codes' => ['TI']],
        ]);

        app(ActivateCampaignAction::class)->execute($campaign);

        Queue::assertPushed(SendCampaignInvitationsJob::class);
    }
}
