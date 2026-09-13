<?php

namespace App\Domains\Questionnaires\Actions;

use App\Domains\Questionnaires\Models\TemplateImport;
use App\Domains\Questionnaires\Support\TemplateImportValidator;
use App\Domains\Questionnaires\Support\XlsxTemplateWorkbookParser;
use App\Models\User;

// FSD §15.3 tahap 1 (ukuran & format file valid) dijalankan di sini sebelum
// menyerahkan payload ke TemplateImportValidator (tahap 2-10, format-agnostic).
// Format 'json' didekode langsung; format 'xlsx' didekode base64 lalu
// dikonversi lewat XlsxTemplateWorkbookParser ke bentuk array yang sama.
// Hasilnya selalu disimpan sebagai TemplateImport staging record — baik yang
// lolos (`validated`) maupun gagal (`failed`) — supaya UI bisa menampilkan
// preview/error tanpa upload ulang (FSD §15.4).
class ValidateTemplateImportAction
{
    private const MAX_CONTENT_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private readonly TemplateImportValidator $validator,
        private readonly XlsxTemplateWorkbookParser $xlsxParser,
    ) {}

    public function execute(string $filename, string $content, User $actor, string $format = 'json'): TemplateImport
    {
        $tenantId = $actor->tenant_id;

        if (strlen($content) > self::MAX_CONTENT_BYTES) {
            return $this->stage(TemplateImport::STATUS_FAILED, $tenantId, $filename, [], [
                ['code' => 'file_too_large', 'message' => 'Ukuran file melebihi batas 2MB.', 'path' => 'file'],
            ], [], null, $actor);
        }

        if ($format === 'xlsx') {
            $decoded = base64_decode($content, true);

            if ($decoded === false) {
                return $this->stage(TemplateImport::STATUS_FAILED, $tenantId, $filename, [], [
                    ['code' => 'invalid_base64', 'message' => 'Konten base64 tidak valid.', 'path' => 'file'],
                ], [], $content, $actor);
            }

            $parsed = $this->xlsxParser->parse($decoded);

            if ($parsed['payload'] === null) {
                return $this->stage(TemplateImport::STATUS_FAILED, $tenantId, $filename, [], $parsed['errors'], [], $content, $actor);
            }

            $payload = $parsed['payload'];
        } else {
            $payload = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($payload)) {
                return $this->stage(TemplateImport::STATUS_FAILED, $tenantId, $filename, [], [
                    ['code' => 'invalid_json', 'message' => 'File bukan JSON yang valid: '.json_last_error_msg(), 'path' => 'file'],
                ], [], $content, $actor);
            }
        }

        $result = $this->validator->validate($payload, $tenantId);
        $status = $result['errors'] === [] ? TemplateImport::STATUS_VALIDATED : TemplateImport::STATUS_FAILED;

        return $this->stage($status, $tenantId, $filename, $payload, $result['errors'], $result['warnings'], $content, $actor, $result['preview']);
    }

    /**
     * @param  array<int, array{code: string, message: string, path: string}>  $errors
     * @param  array<int, array{code: string, message: string, path: string}>  $warnings
     */
    private function stage(
        string $status,
        string $tenantId,
        string $filename,
        array $payload,
        array $errors,
        array $warnings,
        ?string $rawContentForChecksum,
        User $actor,
        ?array $preview = null,
    ): TemplateImport {
        return TemplateImport::query()->create([
            'tenant_id' => $tenantId,
            'filename' => $filename,
            'raw_payload' => $payload,
            'status' => $status,
            'errors_json' => $errors,
            'warnings_json' => $warnings,
            'preview_json' => $preview ?? ['template_code' => null, 'sections' => 0, 'questions' => 0, 'options' => 0],
            'checksum' => $rawContentForChecksum !== null ? hash('sha256', $rawContentForChecksum) : null,
            'uploaded_by' => $actor->id,
            'expires_at' => now()->addHour(),
        ]);
    }
}
