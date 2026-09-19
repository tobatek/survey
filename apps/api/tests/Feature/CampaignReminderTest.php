<?php

namespace Tests\Feature;

use App\Domains\Campaigns\Actions\DispatchCampaignRemindersAction;
use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\DeliveryAttempt;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Students\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignReminderTest extends TestCase
{
    use RefreshDatabase;

    private function makeEligibility(Campaign $campaign, string $status = Eligibility::STATUS_INVITED): Eligibility
    {
        $student = Student::factory()->create(['tenant_id' => 'default', 'institutional_email' => 'mhs@example.ac.id']);

        $member = AudienceMember::factory()->create([
            'campaign_id' => $campaign->id,
            'subject_type' => AudienceMember::SUBJECT_STUDENT,
            'subject_id' => $student->id,
        ]);

        return Eligibility::factory()->create([
            'campaign_id' => $campaign->id,
            'audience_member_id' => $member->id,
            'status' => $status,
        ]);
    }

    private function recordAttempt(Eligibility $eligibility, int $attemptNumber, \DateTimeInterface $sentAt): void
    {
        DeliveryAttempt::factory()->create([
            'eligibility_id' => $eligibility->id,
            'attempt_number' => $attemptNumber,
            'status' => DeliveryAttempt::STATUS_SENT,
            'sent_at' => $sentAt,
        ]);
    }

    private function activeCampaign(array $overrides = []): Campaign
    {
        return Campaign::factory()->create(array_merge([
            'status' => Campaign::STATUS_ACTIVE,
            'ends_at' => now()->addWeek(),
        ], $overrides));
    }

    #[Test]
    public function a_reminder_is_sent_when_past_the_minimum_interval(): void
    {
        Notification::fake();
        $this->travelTo(now()->setTime(10, 0));

        $campaign = $this->activeCampaign();
        $eligibility = $this->makeEligibility($campaign);
        $this->recordAttempt($eligibility, 1, now()->subDays(4));

        $sent = app(DispatchCampaignRemindersAction::class)->execute($campaign);

        $this->assertSame(1, $sent);
        $this->assertDatabaseHas('delivery_attempts', [
            'eligibility_id' => $eligibility->id,
            'attempt_number' => 2,
            'status' => DeliveryAttempt::STATUS_SENT,
        ]);
    }

    #[Test]
    public function no_reminder_when_interval_has_not_passed(): void
    {
        Notification::fake();
        $this->travelTo(now()->setTime(10, 0));

        $campaign = $this->activeCampaign();
        $eligibility = $this->makeEligibility($campaign);
        $this->recordAttempt($eligibility, 1, now()->subDay());

        $sent = app(DispatchCampaignRemindersAction::class)->execute($campaign);

        $this->assertSame(0, $sent);
        $this->assertDatabaseMissing('delivery_attempts', ['eligibility_id' => $eligibility->id, 'attempt_number' => 2]);
    }

    #[Test]
    public function no_reminder_once_max_attempts_reached(): void
    {
        Notification::fake();
        $this->travelTo(now()->setTime(10, 0));

        $campaign = $this->activeCampaign();
        $eligibility = $this->makeEligibility($campaign);
        $this->recordAttempt($eligibility, 1, now()->subDays(10));
        $this->recordAttempt($eligibility, 2, now()->subDays(5));
        $this->recordAttempt($eligibility, 3, now()->subDays(4));

        $sent = app(DispatchCampaignRemindersAction::class)->execute($campaign);

        $this->assertSame(0, $sent);
    }

    #[Test]
    public function completed_eligibilities_are_never_reminded(): void
    {
        Notification::fake();
        $this->travelTo(now()->setTime(10, 0));

        $campaign = $this->activeCampaign();
        $eligibility = $this->makeEligibility($campaign, Eligibility::STATUS_COMPLETED);
        $this->recordAttempt($eligibility, 1, now()->subDays(4));

        $sent = app(DispatchCampaignRemindersAction::class)->execute($campaign);

        $this->assertSame(0, $sent);
    }

    #[Test]
    public function no_reminder_during_quiet_hours(): void
    {
        Notification::fake();

        $campaign = $this->activeCampaign(['timezone' => 'Asia/Jakarta']);
        $eligibility = $this->makeEligibility($campaign);
        $this->recordAttempt($eligibility, 1, now()->subDays(4));

        $this->travelTo(now()->timezone('Asia/Jakarta')->setTime(23, 0)->utc());

        $sent = app(DispatchCampaignRemindersAction::class)->execute($campaign);

        $this->assertSame(0, $sent);
    }

    #[Test]
    public function no_reminder_for_inactive_campaign(): void
    {
        Notification::fake();
        $this->travelTo(now()->setTime(10, 0));

        $campaign = Campaign::factory()->create(['status' => Campaign::STATUS_PAUSED]);
        $eligibility = $this->makeEligibility($campaign);
        $this->recordAttempt($eligibility, 1, now()->subDays(4));

        $sent = app(DispatchCampaignRemindersAction::class)->execute($campaign);

        $this->assertSame(0, $sent);
    }
}
