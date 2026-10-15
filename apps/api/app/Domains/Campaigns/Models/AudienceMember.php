<?php

namespace App\Domains\Campaigns\Models;

use App\Domains\Employees\Models\Employee;
use App\Domains\Students\Models\Student;
use Database\Factories\AudienceMemberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AudienceMember extends Model
{
    /** @use HasFactory<AudienceMemberFactory> */
    use HasFactory, HasUuids;

    public const SUBJECT_STUDENT = 'student';

    public const SUBJECT_EMPLOYEE = 'employee';

    protected static function newFactory(): AudienceMemberFactory
    {
        return AudienceMemberFactory::new();
    }

    protected $fillable = [
        'campaign_id',
        'subject_type',
        'subject_id',
        'attributes_snapshot_json',
        'snapshot_hash',
    ];

    protected function casts(): array
    {
        return [
            'attributes_snapshot_json' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function eligibility(): HasOne
    {
        return $this->hasOne(Eligibility::class);
    }

    // subject_id menunjuk salah satu dari dua tabel berbeda (students/
    // employees) tergantung subject_type — tidak ada morphMap global di
    // proyek ini, jadi resolusinya manual di sini alih-alih relasi
    // polymorphic Eloquent bawaan.
    public function subject(): Student|Employee|null
    {
        return match ($this->subject_type) {
            self::SUBJECT_STUDENT => Student::find($this->subject_id),
            self::SUBJECT_EMPLOYEE => Employee::find($this->subject_id),
            default => null,
        };
    }
}
