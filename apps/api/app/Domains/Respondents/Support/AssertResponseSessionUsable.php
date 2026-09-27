<?php

namespace App\Domains\Respondents\Support;

use App\Domains\Respondents\Exceptions\SurveyAccessException;
use App\Domains\Respondents\Models\ResponseSession;

// Dipakai LoadDraftAction & SaveDraftAction supaya kedua endpoint draft
// (GET dan PUT) menolak sesi yang sudah selesai/kedaluwarsa dengan kode
// error yang sama persis (FSD §11.5).
class AssertResponseSessionUsable
{
    public function execute(ResponseSession $session): void
    {
        if ($session->status === ResponseSession::STATUS_COMPLETED) {
            throw new SurveyAccessException('ALREADY_COMPLETED', 'Anda sudah menyelesaikan survei ini.');
        }

        if ($session->status === ResponseSession::STATUS_EXPIRED || $session->expires_at?->isPast()) {
            throw new SurveyAccessException('SESSION_EXPIRED', 'Waktu pengisian survei ini sudah habis.');
        }
    }
}
