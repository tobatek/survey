<?php

namespace App\Domains\Campaigns\DTO;

// FSD §10.1 — filter target builder. Semua field array-of-string agar
// mendukung multi-value (mis. beberapa prodi sekaligus). `positionNames`
// sengaja dipisah dari field lain: hanya boleh terisi kalau pemanggil sudah
// lolos pemeriksaan role lpm_admin (FSD §9 "filter jabatan sensitif tidak
// otomatis tersedia untuk semua operator") — pemeriksaan itu terjadi di
// controller, DTO ini hanya wadah data yang sudah divalidasi.
final readonly class AudienceFilterDTO
{
    public function __construct(
        public ?array $facultyNames = null,
        public ?array $programStudyCodes = null,
        public ?array $degreeLevels = null,
        public ?array $cohortYears = null,
        public ?array $studentStatuses = null,
        public ?array $employeeTypes = null,
        public ?array $workUnitNames = null,
        public ?array $employeeStatuses = null,
        public ?array $positionNames = null,
        public ?array $includeStudentIds = null,
        public ?array $excludeStudentIds = null,
        public ?array $includeEmployeeIds = null,
        public ?array $excludeEmployeeIds = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            facultyNames: $data['faculty_names'] ?? null,
            programStudyCodes: $data['program_study_codes'] ?? null,
            degreeLevels: $data['degree_levels'] ?? null,
            cohortYears: $data['cohort_years'] ?? null,
            studentStatuses: $data['student_statuses'] ?? null,
            employeeTypes: $data['employee_types'] ?? null,
            workUnitNames: $data['work_unit_names'] ?? null,
            employeeStatuses: $data['employee_statuses'] ?? null,
            positionNames: $data['position_names'] ?? null,
            includeStudentIds: $data['include_student_ids'] ?? null,
            excludeStudentIds: $data['exclude_student_ids'] ?? null,
            includeEmployeeIds: $data['include_employee_ids'] ?? null,
            excludeEmployeeIds: $data['exclude_employee_ids'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'faculty_names' => $this->facultyNames,
            'program_study_codes' => $this->programStudyCodes,
            'degree_levels' => $this->degreeLevels,
            'cohort_years' => $this->cohortYears,
            'student_statuses' => $this->studentStatuses,
            'employee_types' => $this->employeeTypes,
            'work_unit_names' => $this->workUnitNames,
            'employee_statuses' => $this->employeeStatuses,
            'position_names' => $this->positionNames,
            'include_student_ids' => $this->includeStudentIds,
            'exclude_student_ids' => $this->excludeStudentIds,
            'include_employee_ids' => $this->includeEmployeeIds,
            'exclude_employee_ids' => $this->excludeEmployeeIds,
        ];
    }
}
