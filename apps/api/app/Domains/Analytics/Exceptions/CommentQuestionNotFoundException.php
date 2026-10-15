<?php

namespace App\Domains\Analytics\Exceptions;

use RuntimeException;

// Dilempar CampaignCommentsAction ketika question_code yang diminta tidak
// ada di snapshot campaign, atau ada tapi bukan tipe short_text/long_text
// (endpoint .../comments cuma untuk esai -- lihat QuestionResultCalculator
// yang juga membatasi isi jawaban esai TIDAK pernah dikirim lewat endpoint
// .../questions, hanya n-nya).
class CommentQuestionNotFoundException extends RuntimeException
{
    public function __construct(string $questionCode)
    {
        parent::__construct("Pertanyaan '{$questionCode}' tidak ditemukan atau bukan tipe esai (short_text/long_text).");
    }
}
