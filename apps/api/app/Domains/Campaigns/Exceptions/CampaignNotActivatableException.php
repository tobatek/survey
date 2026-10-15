<?php

namespace App\Domains\Campaigns\Exceptions;

use RuntimeException;

// Dilempar oleh ActivateCampaignAction ketika precondition FSD §10.2 tidak
// terpenuhi (status invalid, target belum diisi, atau target kosong /
// RULE-C01). `code` machine-readable dipakai controller untuk memilih pesan.
class CampaignNotActivatableException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
