<?php

namespace Database\Factories;

use App\Domains\Questionnaires\Models\Questionnaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Questionnaire>
 */
class QuestionnaireFactory extends Factory
{
    protected $model = Questionnaire::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 'default',
            'code' => 'SKM-'.fake()->unique()->numerify('####'),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => 'kepuasan',
            'audience_type' => 'student',
            'identity_mode' => 'anonymous',
            'status' => Questionnaire::STATUS_DRAFT,
            'allow_draft_save' => true,
            'lock_version' => 1,
        ];
    }
}
