<?php

namespace App\Domains\Campaigns\Events;

use App\Domains\Campaigns\Models\Campaign;

// Didispatch ActivateCampaignAction setelah transaction commit (FSD §10.2
// langkah 7: "dispatch notification jobs setelah transaction commit").
// Belum ada listener di slice ini — dipakai mulai slice 5 (notifikasi).
class CampaignActivated
{
    public function __construct(public readonly Campaign $campaign) {}
}
