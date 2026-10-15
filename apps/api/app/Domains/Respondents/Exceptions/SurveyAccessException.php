<?php

namespace App\Domains\Respondents\Exceptions;

use RuntimeException;

// FSD §11.5 — kode error standar submit/start flow (SURVEY_NOT_STARTED,
// SURVEY_PAUSED, SURVEY_CLOSED, NOT_ELIGIBLE, ALREADY_COMPLETED, dst).
// `errorCode` machine-readable dipakai controller memilih status HTTP +
// pesan, meniru pola CampaignNotActivatableException (Fase 3).
class SurveyAccessException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
