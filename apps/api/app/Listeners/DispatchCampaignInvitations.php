<?php

namespace App\Listeners;

use App\Domains\Campaigns\Events\CampaignActivated;
use App\Jobs\SendCampaignInvitationsJob;

// FSD §10.2 langkah 7 "dispatch notification jobs setelah transaction
// commit". Terhubung otomatis lewat Laravel event auto-discovery (nama
// method handle() bertipe CampaignActivated cukup, tidak perlu registrasi
// manual di provider manapun).
class DispatchCampaignInvitations
{
    public function handle(CampaignActivated $event): void
    {
        dispatch(new SendCampaignInvitationsJob($event->campaign));
    }
}
