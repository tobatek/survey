<?php

namespace App\Domains\Campaigns\Models;

use Database\Factories\DeliveryAttemptFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAttempt extends Model
{
    /** @use HasFactory<DeliveryAttemptFactory> */
    use HasFactory, HasUuids;

    public const CHANNEL_MAIL = 'mail';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_FAILED = 'failed';

    protected static function newFactory(): DeliveryAttemptFactory
    {
        return DeliveryAttemptFactory::new();
    }

    protected $fillable = [
        'eligibility_id',
        'channel',
        'status',
        'provider_message_id',
        'attempt_number',
        'sent_at',
        'delivered_at',
        'failed_at',
        'error_code',
        'sanitized_error',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function eligibility(): BelongsTo
    {
        return $this->belongsTo(Eligibility::class);
    }
}
