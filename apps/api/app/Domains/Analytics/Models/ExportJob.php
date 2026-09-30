<?php

namespace App\Domains\Analytics\Models;

use App\Domains\Campaigns\Models\Campaign;
use App\Models\User;
use Database\Factories\ExportJobFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportJob extends Model
{
    /** @use HasFactory<ExportJobFactory> */
    use HasFactory, HasUuids;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const FORMAT_XLSX = 'xlsx';

    // TTL unduhan file setelah sukses (FSD §14.2 langkah 7 "signed download
    // URL berumur pendek" -- ini TTL file/row, bukan TTL link itu sendiri).
    public const DOWNLOAD_TTL_HOURS = 24;

    // TTL link signed route, jauh lebih pendek dari TTL file -- dibuat ulang
    // tiap kali status di-poll, tidak pernah disimpan.
    public const SIGNED_URL_TTL_MINUTES = 15;

    // Fallback expiry untuk job yang tidak pernah mencapai status terminal
    // (macet di queued/processing) -- expires_at diisi nilai ini saat
    // create, lalu ditimpa DOWNLOAD_TTL_HOURS begitu sukses.
    public const MAX_PROCESSING_WINDOW_HOURS = 6;

    protected static function newFactory(): ExportJobFactory
    {
        return ExportJobFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'campaign_id',
        'requested_by',
        'format',
        'filter_json',
        'filter_hash',
        'include_comments',
        'status',
        'file_path',
        'file_size',
        'checksum',
        'error_code',
        'requested_at',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'filter_json' => 'array',
            'include_comments' => 'boolean',
            'file_size' => 'integer',
            'requested_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
