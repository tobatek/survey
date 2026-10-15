<?php

namespace App\Console\Commands;

use App\Domains\Campaigns\Actions\ActivateCampaignAction;
use App\Domains\Campaigns\Exceptions\CampaignActivationInProgressException;
use App\Domains\Campaigns\Exceptions\CampaignNotActivatableException;
use App\Domains\Campaigns\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// FSD §10.4/§18.3 — campaign draft dengan starts_at tercapai diaktifkan
// otomatis (keputusan slice: tidak ada status 'scheduled' terpisah, cukup
// draft+starts_at). Satu campaign gagal (target belum diisi/kosong/lock
// sedang dipegang proses lain) tidak boleh menghentikan yang lain.
class ActivateDueCampaignsCommand extends Command
{
    protected $signature = 'campaigns:activate-due';

    protected $description = 'Aktifkan campaign draft yang starts_at-nya sudah tercapai';

    public function handle(ActivateCampaignAction $action): int
    {
        $campaigns = Campaign::query()
            ->where('status', Campaign::STATUS_DRAFT)
            ->whereNotNull('starts_at')
            ->whereNotNull('target_rule_json')
            ->where('starts_at', '<=', now())
            ->get();

        $activated = 0;

        foreach ($campaigns as $campaign) {
            try {
                $action->execute($campaign);
                $activated++;
            } catch (CampaignNotActivatableException|CampaignActivationInProgressException $e) {
                Log::warning('Gagal auto-aktivasi campaign terjadwal.', [
                    'campaign_id' => $campaign->id,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Mengaktifkan {$activated} dari {$campaigns->count()} campaign due.");

        return self::SUCCESS;
    }
}
