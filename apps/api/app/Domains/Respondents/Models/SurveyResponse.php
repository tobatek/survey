<?php

namespace App\Domains\Respondents\Models;

use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Employees\Models\Employee;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Students\Models\Student;
use Database\Factories\SurveyResponseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Nama kelas sengaja `SurveyResponse`, bukan `Response`, supaya tidak
// bentrok konsep dengan Illuminate\Http\Response yang lazim dipakai di
// controller. Table tetap `responses` (lihat migrasi).
class SurveyResponse extends Model
{
    /** @use HasFactory<SurveyResponseFactory> */
    use HasFactory, HasUuids;

    protected $table = 'responses';

    public $timestamps = false;

    protected static function newFactory(): SurveyResponseFactory
    {
        return SurveyResponseFactory::new();
    }

    protected $fillable = [
        'campaign_id',
        'snapshot_id',
        'response_session_id',
        'mode',
        'subject_type',
        'subject_id',
        'segment_snapshot_json',
        'submission_nonce_hash',
        'submitted_at',
        'response_duration_seconds',
        'schema_version',
        'tenant_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'segment_snapshot_json' => 'array',
            'submitted_at' => 'datetime',
            'created_at' => 'datetime',
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(ResponseSession::class, 'response_session_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'response_id');
    }

    public function subject(): Student|Employee|null
    {
        return match ($this->subject_type) {
            AudienceMember::SUBJECT_STUDENT => Student::find($this->subject_id),
            AudienceMember::SUBJECT_EMPLOYEE => Employee::find($this->subject_id),
            default => null,
        };
    }
}
