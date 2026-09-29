<?php

namespace App\Domains\Analytics\Support;

use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

// FSD §13.2 (Fase 5 slice 3) -- menerapkan filter ke query, SELALU berbasis
// kolom snapshot immutable, bukan tabel master live (students/employees).
// Dua sumber kolom beda bentuk (lihat komentar migrasi masing-masing):
// - `audience_members.subject_type` kolom asli + `attributes_snapshot_json`
//   (dipakai funnel eligibility, FSD §10.2 SNAPSHOT_ATTRIBUTES).
// - `responses.segment_snapshot_json` (dipakai hasil per pertanyaan) --
//   `responses.subject_type` TIDAK bisa dipakai karena mode anonymous
//   sengaja tidak mengisinya (kontrak privasi Fase 4); jenis responden di
//   sini di-infer dari keberadaan key pembeda di JSON.
final class SegmentFilterScope
{
    public static function applyToAudienceMemberScope(Builder $query, AnalyticsFilterDTO $filter): void
    {
        if ($filter->respondentTypes !== null && $filter->respondentTypes !== []) {
            $query->whereIn('audience_members.subject_type', $filter->respondentTypes);
        }

        self::applyJsonIn($query, 'audience_members.attributes_snapshot_json', 'faculty_name', $filter->facultyNames);
        self::applyJsonIn($query, 'audience_members.attributes_snapshot_json', 'program_study_code', $filter->programStudyCodes);
        self::applyJsonIn($query, 'audience_members.attributes_snapshot_json', 'degree_level', $filter->degreeLevels);
        self::applyJsonIn($query, 'audience_members.attributes_snapshot_json', 'cohort_year', $filter->cohortYears);
        self::applyJsonIn($query, 'audience_members.attributes_snapshot_json', 'work_unit_name', $filter->workUnitNames);
        self::applyJsonIn($query, 'audience_members.attributes_snapshot_json', 'employee_type', $filter->employeeTypes);
    }

    public static function applyToResponseScope(Builder $query, AnalyticsFilterDTO $filter): void
    {
        if ($filter->respondentTypes !== null && $filter->respondentTypes !== []) {
            $query->where(function (Builder $inner) use ($filter) {
                foreach ($filter->respondentTypes as $type) {
                    $marker = $type === 'student' ? 'program_study_code' : 'employee_type';
                    $inner->orWhereRaw("responses.segment_snapshot_json->>'{$marker}' is not null");
                }
            });
        }

        self::applyJsonIn($query, 'responses.segment_snapshot_json', 'faculty_name', $filter->facultyNames);
        self::applyJsonIn($query, 'responses.segment_snapshot_json', 'program_study_code', $filter->programStudyCodes);
        self::applyJsonIn($query, 'responses.segment_snapshot_json', 'degree_level', $filter->degreeLevels);
        self::applyJsonIn($query, 'responses.segment_snapshot_json', 'cohort_year', $filter->cohortYears);
        self::applyJsonIn($query, 'responses.segment_snapshot_json', 'work_unit_name', $filter->workUnitNames);
        self::applyJsonIn($query, 'responses.segment_snapshot_json', 'employee_type', $filter->employeeTypes);

        if ($filter->submittedFrom !== null) {
            $query->whereDate('responses.submitted_at', '>=', $filter->submittedFrom);
        }

        if ($filter->submittedTo !== null) {
            $query->whereDate('responses.submitted_at', '<=', $filter->submittedTo);
        }
    }

    /**
     * @param  ?list<string>  $values
     */
    private static function applyJsonIn(Builder $query, string $column, string $key, ?array $values): void
    {
        if ($values === null || $values === []) {
            return;
        }

        $query->whereIn(DB::raw("({$column}->>'{$key}')"), $values);
    }
}
