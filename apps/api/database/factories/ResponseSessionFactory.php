<?php

namespace Database\Factories;

use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Respondents\Models\ResponseSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResponseSession>
 */
class ResponseSessionFactory extends Factory
{
    protected $model = ResponseSession::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'snapshot_id' => QuestionnaireSnapshot::factory(),
            'mode' => ResponseSession::MODE_ANONYMOUS,
            'anonymous_nonce_hash' => hash('sha256', (string) str()->uuid()),
            'segment_snapshot_json' => [
                'program_study_code' => fake()->bothify('PS-##'),
                'cohort_year' => (string) fake()->year(),
            ],
            'status' => ResponseSession::STATUS_DRAFT,
            'started_at' => now(),
            'tenant_id' => 'default',
        ];
    }

    public function identified(): static
    {
        return $this->state(fn () => [
            'mode' => ResponseSession::MODE_IDENTIFIED,
            'anonymous_nonce_hash' => null,
            'eligibility_id' => Eligibility::factory(),
            'subject_type' => AudienceMember::SUBJECT_STUDENT,
            'subject_id' => (string) str()->uuid(),
        ]);
    }
}
