<?php

namespace App\Domains\Students\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): StudentFactory
    {
        return StudentFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'source_system',
        'source_id',
        'nim',
        'normalized_nim',
        'name',
        'normalized_name',
        'institutional_email',
        'faculty_name',
        'program_study_code',
        'program_study_name',
        'degree_level',
        'cohort_year',
        'status',
        'source_updated_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'source_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
