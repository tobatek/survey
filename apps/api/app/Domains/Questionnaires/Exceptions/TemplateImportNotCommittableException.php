<?php

namespace App\Domains\Questionnaires\Exceptions;

use RuntimeException;

// Dilempar oleh CommitTemplateImportAction ketika staging record tidak bisa
// dikonfirmasi — FSD §15.4: hasil validasi harus lolos, staging punya expiry,
// dan template yang sudah di-commit tidak bisa di-commit ulang.
class TemplateImportNotCommittableException extends RuntimeException
{
    /**
     * @param  array<int, array{code: string, message: string, path?: string}>  $errors
     */
    public function __construct(public readonly string $reasonCode, string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
