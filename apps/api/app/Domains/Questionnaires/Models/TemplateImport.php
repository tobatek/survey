<?php

namespace App\Domains\Questionnaires\Models;

use App\Models\User;
use Database\Factories\TemplateImportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// FSD §15.4 — staging record hasil ValidateTemplateImportAction. `status`
// menentukan boleh-tidaknya CommitTemplateImportAction dijalankan (lihat
// TemplateImportNotCommittableException).
class TemplateImport extends Model
{
    /** @use HasFactory<TemplateImportFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PENDING_VALIDATION = 'pending_validation';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_FAILED = 'failed';

    public const STATUS_COMMITTED = 'committed';

    protected static function newFactory(): TemplateImportFactory
    {
        return TemplateImportFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'filename',
        'raw_payload',
        'status',
        'errors_json',
        'warnings_json',
        'preview_json',
        'checksum',
        'uploaded_by',
        'expires_at',
        'committed_at',
        'committed_template_id',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'errors_json' => 'array',
            'warnings_json' => 'array',
            'preview_json' => 'array',
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function committedTemplate(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'committed_template_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
