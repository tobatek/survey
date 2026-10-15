<?php

namespace App\Domains\Questionnaires\Support;

use App\Domains\Questionnaires\Exceptions\QuestionnaireLockConflictException;
use App\Domains\Questionnaires\Models\Questionnaire;

// FSD §7.7 — dipakai oleh SEMUA mutasi builder (questionnaire, section,
// question, option, matrix row): client wajib kirim lock_version yang sedang
// dia lihat (lewat header If-Match) sebelum boleh menulis; setiap mutasi
// sukses menaikkan lock_version questionnaire induknya, walau yang diubah
// cuma satu pertanyaan — supaya editor lain yang sedang melihat questionnaire
// yang sama langsung tahu datanya sudah basi.
class QuestionnaireLockGuard
{
    public function assertMatches(Questionnaire $questionnaire, ?int $ifMatch): void
    {
        if ($ifMatch === null || $ifMatch !== $questionnaire->lock_version) {
            throw new QuestionnaireLockConflictException($questionnaire->lock_version);
        }
    }

    public function bump(Questionnaire $questionnaire): void
    {
        $questionnaire->increment('lock_version');
    }
}
