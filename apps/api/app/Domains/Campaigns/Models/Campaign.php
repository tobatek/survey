<?php

namespace App\Domains\Campaigns\Models;

use App\Domains\Analytics\Models\ExportJob;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Respondents\Models\SurveyResponse;
use App\Models\User;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CLOSED = 'closed';

    protected static function newFactory(): CampaignFactory
    {
        return CampaignFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'questionnaire_id',
        'snapshot_id',
        'status',
        'starts_at',
        'ends_at',
        'timezone',
        'target_rule_json',
        'denominator_count',
        'minimum_segment_size',
        'activated_by',
        'activated_at',
        'paused_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_rule_json' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'activated_at' => 'datetime',
            'paused_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireSnapshot::class, 'snapshot_id');
    }

    public function audienceMembers(): HasMany
    {
        return $this->hasMany(AudienceMember::class);
    }

    public function eligibilities(): HasManyThrough
    {
        return $this->hasManyThrough(Eligibility::class, AudienceMember::class);
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function exportJobs(): HasMany
    {
        return $this->hasMany(ExportJob::class);
    }
}
