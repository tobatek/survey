<?php

namespace App\Domains\Campaigns\Actions;

use App\Domains\Audit\Actions\RecordAuditEventAction;
use App\Domains\Campaigns\Exceptions\CampaignTransitionConflictException;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// FSD §17.4/§10.4 — pause/resume/close. Campaign tidak pakai lock_version/
// If-Match (keputusan slice 1: wizard campaign diedit sekuensial), jadi
// race-safety transisi status dijamin lewat conditional update (WHERE
// status = expected) alih-alih optimistic lock ala Questionnaire.
class TransitionCampaignStatusAction
{
    /** @var array<string, array<int, string>> */
    private const ALLOWED = [
        Campaign::STATUS_ACTIVE => [Campaign::STATUS_PAUSED, Campaign::STATUS_CLOSED],
        Campaign::STATUS_PAUSED => [Campaign::STATUS_ACTIVE, Campaign::STATUS_CLOSED],
    ];

    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function execute(Campaign $campaign, string $targetStatus, ?User $actor = null): Campaign
    {
        $allowed = self::ALLOWED[$campaign->status] ?? [];

        if (! in_array($targetStatus, $allowed, true)) {
            throw new RuntimeException("Transisi status dari '{$campaign->status}' ke '{$targetStatus}' tidak diizinkan.");
        }

        $attributes = ['status' => $targetStatus];

        if ($targetStatus === Campaign::STATUS_PAUSED) {
            $attributes['paused_at'] = now();
        } elseif ($targetStatus === Campaign::STATUS_CLOSED) {
            $attributes['closed_at'] = now();
        }

        $affected = DB::transaction(function () use ($campaign, $targetStatus, $attributes) {
            $affected = Campaign::query()
                ->where('id', $campaign->id)
                ->where('status', $campaign->status)
                ->update($attributes);

            // Fase 4 slice 6 -- eligibility yang belum terminal (belum
            // completed/excluded) ditutup begitu campaign-nya ditutup. Ini
            // satu-satunya jalur aman menandai eligibility mode ANONYMOUS
            // sebagai expired: sesi anonymous sengaja tidak pernah menyimpan
            // eligibility_id (kontrak privasi slice 1), jadi tidak bisa
            // ditelusuri dari sisi sesi -- harus lewat siklus hidup campaign.
            if ($affected > 0 && $targetStatus === Campaign::STATUS_CLOSED) {
                Eligibility::query()
                    ->where('campaign_id', $campaign->id)
                    ->whereNotIn('status', [Eligibility::STATUS_COMPLETED, Eligibility::STATUS_EXCLUDED])
                    ->update(['status' => Eligibility::STATUS_EXPIRED, 'expired_at' => now()]);
            }

            return $affected;
        });

        if ($affected === 0) {
            throw new CampaignTransitionConflictException;
        }

        $this->recordAuditEvent->execute(
            action: 'campaign.transition.'.$targetStatus,
            result: 'success',
            actor: $actor,
            resourceType: 'campaign',
            resourceId: $campaign->id,
        );

        return $campaign->fresh();
    }
}
