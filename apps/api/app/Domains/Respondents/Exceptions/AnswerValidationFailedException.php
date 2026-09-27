<?php

namespace App\Domains\Respondents\Exceptions;

use RuntimeException;

// Meniru App\Domains\Questionnaires\Exceptions\QuestionnaireValidationFailedException
// -- dilempar SaveDraftAction (autosave) maupun SubmitResponseAction (submit
// final) ketika ada jawaban yang bentuknya tidak sesuai tipe question di
// snapshot, atau (khusus submit) ada pertanyaan wajib yang belum terjawab
// (FSD §17.1: envelope error VALIDATION_FAILED).
class AnswerValidationFailedException extends RuntimeException
{
    /**
     * @param  array<int, array{code: string, field: string, message: string}>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Jawaban belum valid.');
    }
}
