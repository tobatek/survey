<?php

namespace App\Domains\Respondents\Exceptions;

use RuntimeException;

// Meniru App\Domains\Questionnaires\Exceptions\QuestionnaireValidationFailedException
// -- dilempar SaveDraftAction ketika ada jawaban yang bentuknya tidak sesuai
// tipe question di snapshot (FSD §17.1: envelope error VALIDATION_FAILED).
class DraftValidationFailedException extends RuntimeException
{
    /**
     * @param  array<int, array{code: string, field: string, message: string}>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Jawaban draft belum valid.');
    }
}
