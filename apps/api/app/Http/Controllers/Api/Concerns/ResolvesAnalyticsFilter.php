<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use App\Domains\Campaigns\Models\AudienceMember;
use Illuminate\Http\Request;

// Diekstrak dari AnalyticsController (Fase 5 slice 2-3) supaya
// ExportController (slice 5) memakai aturan validasi filter yang identik --
// mencegah field filter drift antara endpoint baca JSON dan endpoint export.
trait ResolvesAnalyticsFilter
{
    private function filterFromRequest(Request $request): AnalyticsFilterDTO
    {
        $data = $request->validate([
            'respondent_types' => ['sometimes', 'array'],
            'respondent_types.*' => ['string', 'in:'.AudienceMember::SUBJECT_STUDENT.','.AudienceMember::SUBJECT_EMPLOYEE],
            'faculty_names' => ['sometimes', 'array'],
            'faculty_names.*' => ['string'],
            'program_study_codes' => ['sometimes', 'array'],
            'program_study_codes.*' => ['string'],
            'degree_levels' => ['sometimes', 'array'],
            'degree_levels.*' => ['string'],
            'cohort_years' => ['sometimes', 'array'],
            'cohort_years.*' => ['string'],
            'work_unit_names' => ['sometimes', 'array'],
            'work_unit_names.*' => ['string'],
            'employee_types' => ['sometimes', 'array'],
            'employee_types.*' => ['string'],
            'submitted_from' => ['sometimes', 'date'],
            'submitted_to' => ['sometimes', 'date', 'after_or_equal:submitted_from'],
            'question_search' => ['sometimes', 'string', 'max:255'],
        ]);

        return AnalyticsFilterDTO::fromArray($data);
    }
}
