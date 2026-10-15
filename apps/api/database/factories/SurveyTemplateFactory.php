<?php

namespace Database\Factories;

use App\Domains\Questionnaires\Models\SurveyTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyTemplate>
 */
class SurveyTemplateFactory extends Factory
{
    protected $model = SurveyTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 'default',
            'code' => 'TPL-'.fake()->unique()->numerify('####'),
            'title' => fake()->sentence(3),
            'audience_type' => 'student',
            'identity_mode' => 'anonymous',
            'language' => 'id',
            'schema_version' => 1,
            'status' => 'active',
        ];
    }
}
