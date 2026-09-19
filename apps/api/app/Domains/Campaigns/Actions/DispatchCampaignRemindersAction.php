<?php

namespace App\Domains\Campaigns\Actions;

use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\DeliveryAttempt;
use App\Domains\Campaigns\Models\Eligibility;
use App\Notifications\CampaignInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

// FSD §10.5 — reminder. Angka di bawah ini (batas jumlah, jarak minimal,
// jam tenang) TIDAK disebutkan presisinya di FSD (perlu keputusan MVP),
// sudah dikonfirmasi user: maksimal 3x total pengiriman (undangan +
// reminder), jarak minimal 3 hari antar pengiriman, jam tenang 21:00-07:00
// waktu lokal campaign.
class DispatchCampaignRemindersAction
{
    private const MAX_ATTEMPTS = 3;

    private const MIN_INTERVAL_DAYS = 3;

    private const QUIET_HOURS_START = 21;

    private const QUIET_HOURS_END = 7;

    public function execute(Campaign $campaign): int
    {
        if ($campaign->status !== Campaign::STATUS_ACTIVE) {
            return 0;
        }

        if ($campaign->ends_at !== null && $campaign->ends_at->isPast()) {
            return 0;
        }

        if ($this->isQuietHours($campaign)) {
            return 0;
        }

        $title = $campaign->questionnaire()->value('title') ?? 'Survei';
        $sent = 0;

        $this->dueEligibilities($campaign)
            ->with('audienceMember')
            ->chunkById(200, function ($eligibilities) use (&$sent, $title) {
                foreach ($eligibilities as $eligibility) {
                    if ($this->sendReminder($eligibility, $title)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function isQuietHours(Campaign $campaign): bool
    {
        $hour = now()->timezone($campaign->timezone)->hour;

        return $hour >= self::QUIET_HOURS_START || $hour < self::QUIET_HOURS_END;
    }

    /**
     * Eligibility yang belum selesai, masih diundang (bukan yang belum
     * pernah dikirimi undangan sama sekali), belum mencapai batas
     * pengiriman, dan sudah lewat jarak minimal sejak pengiriman terakhir.
     */
    private function dueEligibilities(Campaign $campaign)
    {
        $latestAttempts = DeliveryAttempt::query()
            ->select('eligibility_id')
            ->selectRaw('max(attempt_number) as latest_attempt_number')
            ->selectRaw('max(sent_at) as latest_sent_at')
            ->groupBy('eligibility_id');

        return Eligibility::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotIn('status', [
                Eligibility::STATUS_COMPLETED,
                Eligibility::STATUS_EXCLUDED,
                Eligibility::STATUS_EXPIRED,
            ])
            ->joinSub($latestAttempts, 'latest_attempts', function ($join) {
                $join->on('latest_attempts.eligibility_id', '=', 'eligibilities.id');
            })
            ->where('latest_attempts.latest_attempt_number', '<', self::MAX_ATTEMPTS)
            ->where(function ($query) {
                $query->whereNull('latest_attempts.latest_sent_at')
                    ->orWhere('latest_attempts.latest_sent_at', '<=', now()->subDays(self::MIN_INTERVAL_DAYS));
            })
            ->select('eligibilities.*', 'latest_attempts.latest_attempt_number');
    }

    private function sendReminder(Eligibility $eligibility, string $questionnaireTitle): bool
    {
        $subject = $eligibility->audienceMember?->subject();
        $email = $subject?->institutional_email;
        $nextAttemptNumber = (int) $eligibility->getAttribute('latest_attempt_number') + 1;

        if (empty($email)) {
            DeliveryAttempt::query()->create([
                'eligibility_id' => $eligibility->id,
                'channel' => DeliveryAttempt::CHANNEL_MAIL,
                'status' => DeliveryAttempt::STATUS_FAILED,
                'attempt_number' => $nextAttemptNumber,
                'failed_at' => now(),
                'error_code' => 'no_email',
                'sanitized_error' => 'Subject tidak memiliki institutional_email.',
            ]);

            return false;
        }

        $rawToken = Str::random(64);

        try {
            Notification::route('mail', $email)->notify(
                new CampaignInvitationNotification($questionnaireTitle, $rawToken, isReminder: true),
            );

            DeliveryAttempt::query()->create([
                'eligibility_id' => $eligibility->id,
                'channel' => DeliveryAttempt::CHANNEL_MAIL,
                'status' => DeliveryAttempt::STATUS_SENT,
                'attempt_number' => $nextAttemptNumber,
                'sent_at' => now(),
            ]);

            $eligibility->update(['invitation_token_hash' => hash('sha256', $rawToken)]);

            return true;
        } catch (Throwable $e) {
            DeliveryAttempt::query()->create([
                'eligibility_id' => $eligibility->id,
                'channel' => DeliveryAttempt::CHANNEL_MAIL,
                'status' => DeliveryAttempt::STATUS_FAILED,
                'attempt_number' => $nextAttemptNumber,
                'failed_at' => now(),
                'error_code' => class_basename($e),
                'sanitized_error' => Str::limit($e->getMessage(), 255),
            ]);

            return false;
        }
    }
}
