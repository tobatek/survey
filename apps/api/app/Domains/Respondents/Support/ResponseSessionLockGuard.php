<?php

namespace App\Domains\Respondents\Support;

use App\Domains\Respondents\Exceptions\DraftLockConflictException;
use App\Domains\Respondents\Models\ResponseSession;

// Meniru App\Domains\Questionnaires\Support\QuestionnaireLockGuard --
// autosave draft wajib membawa lock_version yang sedang dilihat client lewat
// header If-Match sebelum boleh menulis; setiap simpan sukses menaikkan
// lock_version supaya tab/perangkat lain yang sedang melihat sesi yang sama
// tahu draftnya sudah basi.
class ResponseSessionLockGuard
{
    public function assertMatches(ResponseSession $session, ?int $ifMatch): void
    {
        if ($ifMatch === null || $ifMatch !== $session->lock_version) {
            throw new DraftLockConflictException($session->lock_version);
        }
    }

    public function bump(ResponseSession $session): void
    {
        $session->increment('lock_version');
    }
}
