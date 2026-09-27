<?php

namespace App\Domains\Respondents\Exceptions;

use RuntimeException;

// FSD §11.3/§17.1 -- autosave draft wajib membawa If-Match/lock_version.
// Dilempar saat nilai yang dikirim client tidak cocok dengan lock_version
// terkini di DB (draft sudah disimpan dari tab/perangkat lain). Controller
// menangkap ini dan mengembalikan HTTP 409 dengan kode STALE_DRAFT.
class DraftLockConflictException extends RuntimeException
{
    public function __construct(public readonly int $currentLockVersion)
    {
        parent::__construct('Draft sudah diubah dari sesi lain. Muat ulang untuk melihat perubahan terbaru.');
    }
}
