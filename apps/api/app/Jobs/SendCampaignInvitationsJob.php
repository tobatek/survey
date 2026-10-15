<?php

namespace App\Jobs;

use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\DeliveryAttempt;
use App\Domains\Campaigns\Models\Eligibility;
use App\Notifications\CampaignInvitationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

// FSD §10.2 langkah 7 "dispatch notification jobs setelah transaction
// commit" — dipicu App\Listeners\DispatchCampaignInvitations saat event
// CampaignActivated. Queue `notifications` sesuai FSD §18.2.
class SendCampaignInvitationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly Campaign $campaign)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $title = $this->campaign->questionnaire()->value('title') ?? 'Survei';

        $this->campaign->eligibilities()
            ->where('status', Eligibility::STATUS_ELIGIBLE)
            ->with('audienceMember')
            ->chunkById(200, function ($eligibilities) use ($title) {
                foreach ($eligibilities as $eligibility) {
                    $this->sendOne($eligibility, $title);
                }
            });
    }

    private function sendOne(Eligibility $eligibility, string $questionnaireTitle): void
    {
        $subject = $eligibility->audienceMember?->subject();
        $email = $subject?->institutional_email;

        if (empty($email)) {
            DeliveryAttempt::query()->create([
                'eligibility_id' => $eligibility->id,
                'channel' => DeliveryAttempt::CHANNEL_MAIL,
                'status' => DeliveryAttempt::STATUS_FAILED,
                'attempt_number' => 1,
                'failed_at' => now(),
                'error_code' => 'no_email',
                'sanitized_error' => 'Subject tidak memiliki institutional_email.',
            ]);

            return;
        }

        $rawToken = Str::random(64);

        try {
            Notification::route('mail', $email)->notify(
                new CampaignInvitationNotification($questionnaireTitle, $rawToken),
            );

            DeliveryAttempt::query()->create([
                'eligibility_id' => $eligibility->id,
                'channel' => DeliveryAttempt::CHANNEL_MAIL,
                'status' => DeliveryAttempt::STATUS_SENT,
                'attempt_number' => 1,
                'sent_at' => now(),
            ]);

            $eligibility->update([
                'invitation_token_hash' => hash('sha256', $rawToken),
                'status' => Eligibility::STATUS_INVITED,
            ]);
        } catch (Throwable $e) {
            DeliveryAttempt::query()->create([
                'eligibility_id' => $eligibility->id,
                'channel' => DeliveryAttempt::CHANNEL_MAIL,
                'status' => DeliveryAttempt::STATUS_FAILED,
                'attempt_number' => 1,
                'failed_at' => now(),
                'error_code' => class_basename($e),
                'sanitized_error' => Str::limit($e->getMessage(), 255),
            ]);
        }
    }
}
