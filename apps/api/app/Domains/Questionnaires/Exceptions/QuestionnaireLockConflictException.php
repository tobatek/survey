<?php

namespace App\Domains\Questionnaires\Exceptions;

use RuntimeException;

// FSD §7.7 — mutasi builder wajib membawa If-Match/lock_version. Dilempar saat
// nilai yang dikirim client tidak cocok dengan lock_version terkini di DB
// (sudah diubah oleh editor lain). Controller menangkap ini dan
// mengembalikan HTTP 409 sesuai amplop error §17.1.
class QuestionnaireLockConflictException extends RuntimeException
{
    public function __construct(public readonly int $currentLockVersion)
    {
        parent::__construct('Kuesioner sudah diubah oleh sesi lain. Muat ulang untuk melihat perubahan terbaru.');
    }
}
