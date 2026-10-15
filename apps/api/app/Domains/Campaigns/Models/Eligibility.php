<?php

namespace App\Domains\Campaigns\Models;

use Database\Factories\EligibilityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Eligibility extends Model
{
    /** @use HasFactory<EligibilityFactory> */
    use HasFactory, HasUuids;

    public const STATUS_ELIGIBLE = 'eligible';

    public const STATUS_INVITED = 'invited';

    public const STATUS_OPENED = 'opened';

    public const STATUS_STARTED = 'started';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_EXCLUDED = 'excluded';

    protected static function newFactory(): EligibilityFactory
    {
        return EligibilityFactory::new();
    }

    protected $fillable = [
        'campaign_id',
        'audience_member_id',
        'status',
        'invitation_token_hash',
        'opened_at',
        'started_at',
        'completed_at',
        'expired_at',
        'completion_receipt_hash',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function audienceMember(): BelongsTo
    {
        return $this->belongsTo(AudienceMember::class);
    }

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class);
    }
}
