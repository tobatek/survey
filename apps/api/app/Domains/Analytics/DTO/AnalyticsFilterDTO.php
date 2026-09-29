<?php

namespace App\Domains\Analytics\DTO;

// FSD §13.2 (Fase 5 slice 3) — filter halaman hasil, SELALU berbasis
// snapshot attributes (`audience_members.attributes_snapshot_json` /
// `responses.segment_snapshot_json`), bukan data master live. Pola readonly
// DTO sama seperti `App\Domains\Campaigns\DTO\AudienceFilterDTO`, tapi
// sengaja dipisah -- field/semantiknya beda (ini memfilter hasil yang SUDAH
// terjadi, bukan menyusun target audience).
final readonly class AnalyticsFilterDTO
{
    public function __construct(
        public ?array $respondentTypes = null,
        public ?array $facultyNames = null,
        public ?array $programStudyCodes = null,
        public ?array $degreeLevels = null,
        public ?array $cohortYears = null,
        public ?array $workUnitNames = null,
        public ?array $employeeTypes = null,
        public ?string $submittedFrom = null,
        public ?string $submittedTo = null,
        public ?string $questionSearch = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            respondentTypes: $data['respondent_types'] ?? null,
            facultyNames: $data['faculty_names'] ?? null,
            programStudyCodes: $data['program_study_codes'] ?? null,
            degreeLevels: $data['degree_levels'] ?? null,
            cohortYears: $data['cohort_years'] ?? null,
            workUnitNames: $data['work_unit_names'] ?? null,
            employeeTypes: $data['employee_types'] ?? null,
            submittedFrom: $data['submitted_from'] ?? null,
            submittedTo: $data['submitted_to'] ?? null,
            questionSearch: $data['question_search'] ?? null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->respondentTypes === null
            && $this->facultyNames === null
            && $this->programStudyCodes === null
            && $this->degreeLevels === null
            && $this->cohortYears === null
            && $this->workUnitNames === null
            && $this->employeeTypes === null
            && $this->submittedFrom === null
            && $this->submittedTo === null;
    }
}
