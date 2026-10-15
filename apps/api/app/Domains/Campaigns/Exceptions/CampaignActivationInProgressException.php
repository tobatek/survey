<?php

namespace App\Domains\Campaigns\Exceptions;

use RuntimeException;

// Dilempar ActivateCampaignAction ketika Cache::lock per campaign gagal
// didapat dalam batas waktu — indikasi ada proses aktivasi lain yang masih
// berjalan untuk campaign yang sama (FSD §10.2 langkah 1: acquire lock).
class CampaignActivationInProgressException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Aktivasi campaign ini sedang diproses, coba lagi sebentar.');
    }
}
