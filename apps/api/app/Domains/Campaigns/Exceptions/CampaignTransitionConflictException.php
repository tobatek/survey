<?php

namespace App\Domains\Campaigns\Exceptions;

use RuntimeException;

// Dilempar TransitionCampaignStatusAction ketika conditional update (WHERE
// status = expected) tidak mengubah baris apa pun — status campaign sudah
// berubah di request lain sejak dibaca (race). Campaign tidak pakai
// lock_version/If-Match, jadi ini pengganti mekanisme konflik optimistik.
class CampaignTransitionConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Status campaign sudah berubah, muat ulang dan coba lagi.');
    }
}
