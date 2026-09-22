<?php

namespace Database\Factories;

use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use App\Domains\Respondents\Models\ResponseSession;
use App\Domains\Respondents\Models\SurveyResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyResponse>
 */
class SurveyResponseFactory extends Factory
{
    protected $model = SurveyResponse::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'snapshot_id' => QuestionnaireSnapshot::factory(),
            'response_session_id' => ResponseSession::factory(),
            'mode' => ResponseSession::MODE_ANONYMOUS,
            'segment_snapshot_json' => [
                'program_study_code' => fake()->bothify('PS-##'),
                'cohort_year' => (string) fake()->year(),
            ],
            'submission_nonce_hash' => hash('sha256', (string) str()->uuid()),
            'submitted_at' => now(),
            'schema_version' => 1,
            'tenant_id' => 'default',
            'created_at' => now(),
        ];
    }
}
