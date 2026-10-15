<?php

namespace App\Http\Resources;

use App\Domains\Students\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nim' => $this->nim,
            'name' => $this->name,
            'email' => $this->institutional_email,
            'faculty' => $this->faculty_name,
            'program_study' => $this->program_study_name,
            'degree_level' => $this->degree_level,
            'cohort_year' => $this->cohort_year,
            'status' => $this->status,
            'source' => $this->source_system,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
