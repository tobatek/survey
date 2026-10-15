<?php

namespace Database\Factories;

use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudienceMember>
 */
class AudienceMemberFactory extends Factory
{
    protected $model = AudienceMember::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'subject_type' => AudienceMember::SUBJECT_STUDENT,
            'subject_id' => (string) str()->uuid(),
            'attributes_snapshot_json' => [
                'faculty_name' => fake()->word(),
                'program_study_code' => fake()->bothify('PS-##'),
                'cohort_year' => (string) fake()->year(),
            ],
            'snapshot_hash' => fake()->sha256(),
        ];
    }
}
