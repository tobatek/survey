<?php

namespace App\Domains\Respondents\Models;

use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Employees\Models\Employee;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Students\Models\Student;
use Database\Factories\ResponseSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResponseSession extends Model
{
    /** @use HasFactory<ResponseSessionFactory> */
    use HasFactory, HasUuids;

    public const MODE_IDENTIFIED = 'identified';

    public const MODE_ANONYMOUS = 'anonymous';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTING = 'submitting';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    // Fase 4 slice 6 -- TTL sesi draft, rolling (disegarkan tiap autosave,
    // lihat StartResponseSessionAction & SaveDraftAction). Dipakai
    // Console\Commands\ExpireStaleResponseSessionsCommand untuk menandai
    // sesi yang benar-benar ditinggalkan.
    public const DRAFT_TTL_DAYS = 7;

    protected static function newFactory(): ResponseSessionFactory
    {
        return ResponseSessionFactory::new();
    }

    protected $fillable = [
        'campaign_id',
        'snapshot_id',
        'eligibility_id',
        'mode',
        'subject_type',
        'subject_id',
        'anonymous_nonce_hash',
        'segment_snapshot_json',
        'status',
        'draft_answers_json',
        'lock_version',
        'started_at',
        'last_saved_at',
        'expires_at',
        'completed_at',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'segment_snapshot_json' => 'array',
            'draft_answers_json' => 'array',
            'started_at' => 'datetime',
            'last_saved_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireSnapshot::class, 'snapshot_id');
    }

    public function eligibility(): BelongsTo
    {
        return $this->belongsTo(Eligibility::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    // subject_id menunjuk students/employees tergantung subject_type, hanya
    // terisi mode identified — lihat AudienceMember::subject() untuk pola yang
    // sama.
    public function subject(): Student|Employee|null
    {
        return match ($this->subject_type) {
            AudienceMember::SUBJECT_STUDENT => Student::find($this->subject_id),
            AudienceMember::SUBJECT_EMPLOYEE => Employee::find($this->subject_id),
            default => null,
        };
    }
}
