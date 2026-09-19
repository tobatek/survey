<?php

namespace App\Console\Commands;

use App\Domains\Campaigns\Actions\DispatchCampaignRemindersAction;
use App\Domains\Campaigns\Models\Campaign;
use Illuminate\Console\Command;

// FSD §10.5/§18.3 — dijalankan tiap jam (lihat routes/console.php); aman
// dijalankan berkali-kali karena aturan jarak-minimal & jam-tenang dicek
// ulang di dalam DispatchCampaignRemindersAction tiap kali jalan.
class SendCampaignRemindersCommand extends Command
{
    protected $signature = 'campaigns:send-reminders';

    protected $description = 'Kirim email pengingat untuk campaign active yang belum selesai diisi respondennya';

    public function handle(DispatchCampaignRemindersAction $action): int
    {
        $campaigns = Campaign::query()->where('status', Campaign::STATUS_ACTIVE)->get();

        $totalSent = 0;

        foreach ($campaigns as $campaign) {
            $totalSent += $action->execute($campaign);
        }

        $this->info("Mengirim {$totalSent} pengingat untuk {$campaigns->count()} campaign active.");

        return self::SUCCESS;
    }
}
