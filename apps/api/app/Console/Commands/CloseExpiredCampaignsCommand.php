<?php

namespace App\Console\Commands;

use App\Domains\Campaigns\Actions\TransitionCampaignStatusAction;
use App\Domains\Campaigns\Exceptions\CampaignTransitionConflictException;
use App\Domains\Campaigns\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// FSD §10.4/§18.3 — campaign active yang ends_at-nya sudah lewat ditutup
// otomatis. Reuse TransitionCampaignStatusAction yang sama dengan endpoint
// manual close, supaya audit trail konsisten.
class CloseExpiredCampaignsCommand extends Command
{
    protected $signature = 'campaigns:close-expired';

    protected $description = 'Tutup campaign active yang ends_at-nya sudah lewat';

    public function handle(TransitionCampaignStatusAction $action): int
    {
        $campaigns = Campaign::query()
            ->where('status', Campaign::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        $closed = 0;

        foreach ($campaigns as $campaign) {
            try {
                $action->execute($campaign, Campaign::STATUS_CLOSED);
                $closed++;
            } catch (CampaignTransitionConflictException $e) {
                Log::warning('Gagal auto-tutup campaign terjadwal (konflik status).', [
                    'campaign_id' => $campaign->id,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Menutup {$closed} dari {$campaigns->count()} campaign expired.");

        return self::SUCCESS;
    }
}
