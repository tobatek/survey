<?php

namespace App\Domains\Questionnaires\Models;

use App\Domains\Campaigns\Models\Campaign;
use App\Models\User;
use Database\Factories\QuestionnaireFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Questionnaire extends Model
{
    /** @use HasFactory<QuestionnaireFactory> */
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CLOSED = 'closed';

    protected static function newFactory(): QuestionnaireFactory
    {
        return QuestionnaireFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'code',
        'title',
        'description',
        'category',
        'audience_type',
        'identity_mode',
        'academic_period_id',
        'status',
        'allow_draft_save',
        'privacy_notice',
        'confirmation_text',
        'structure_version',
        'lock_version',
        'snapshot_checksum',
        'template_id',
        'created_by',
        'finalized_by',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'allow_draft_save' => 'boolean',
            'finalized_at' => 'datetime',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(QuestionnaireSection::class)->orderBy('sort_order');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(QuestionnaireSnapshot::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
