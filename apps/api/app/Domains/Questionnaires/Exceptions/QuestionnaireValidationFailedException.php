<?php

namespace App\Domains\Questionnaires\Exceptions;

use RuntimeException;

// Dilempar oleh FinalizeQuestionnaireAction ketika ValidateQuestionnaireStructureAction
// menemukan error (bukan sekadar warning) — FSD §7.5.
class QuestionnaireValidationFailedException extends RuntimeException
{
    /**
     * @param  array<int, array{code: string, message: string}>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Struktur kuesioner belum valid untuk difinalisasi.');
    }
}
