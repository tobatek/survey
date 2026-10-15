<?php

namespace App\Domains\Respondents\Models;

use Database\Factories\IdempotencyKeyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    /** @use HasFactory<IdempotencyKeyFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public $timestamps = false;

    protected static function newFactory(): IdempotencyKeyFactory
    {
        return IdempotencyKeyFactory::new();
    }

    protected $fillable = [
        'scope_hash',
        'key_hash',
        'request_hash',
        'status',
        'response_reference',
        'expires_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'response_reference');
    }
}
