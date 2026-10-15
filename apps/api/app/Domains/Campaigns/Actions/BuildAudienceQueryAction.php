<?php

namespace App\Domains\Campaigns\Actions;

use App\Domains\Campaigns\DTO\AudienceFilterDTO;
use App\Domains\Employees\Models\Employee;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Builder;

// FSD §10.1 — bangun query mahasiswa & pegawai dari filter target builder.
// Query dikembalikan terpisah (bukan union) karena kolom & breakdown per
// subject_type berbeda makna; penjumlahan dilakukan pemanggil.
class BuildAudienceQueryAction
{
    public function studentQuery(AudienceFilterDTO $filter, string $tenantId): Builder
    {
        return Student::query()
            ->where('tenant_id', $tenantId)
            ->when($filter->facultyNames, fn (Builder $q, array $v) => $q->whereIn('faculty_name', $v))
            ->when($filter->programStudyCodes, fn (Builder $q, array $v) => $q->whereIn('program_study_code', $v))
            ->when($filter->degreeLevels, fn (Builder $q, array $v) => $q->whereIn('degree_level', $v))
            ->when($filter->cohortYears, fn (Builder $q, array $v) => $q->whereIn('cohort_year', $v))
            ->when($filter->studentStatuses, fn (Builder $q, array $v) => $q->whereIn('status', $v))
            ->when($filter->includeStudentIds, fn (Builder $q, array $v) => $q->whereIn('id', $v))
            ->when($filter->excludeStudentIds, fn (Builder $q, array $v) => $q->whereNotIn('id', $v));
    }

    public function employeeQuery(AudienceFilterDTO $filter, string $tenantId): Builder
    {
        return Employee::query()
            ->where('tenant_id', $tenantId)
            ->when($filter->employeeTypes, fn (Builder $q, array $v) => $q->whereIn('employee_type', $v))
            ->when($filter->workUnitNames, fn (Builder $q, array $v) => $q->whereIn('work_unit_name', $v))
            ->when($filter->employeeStatuses, fn (Builder $q, array $v) => $q->whereIn('status', $v))
            ->when($filter->positionNames, fn (Builder $q, array $v) => $q->whereIn('position_name', $v))
            ->when($filter->includeEmployeeIds, fn (Builder $q, array $v) => $q->whereIn('id', $v))
            ->when($filter->excludeEmployeeIds, fn (Builder $q, array $v) => $q->whereNotIn('id', $v));
    }
}
