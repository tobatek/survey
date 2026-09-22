<?php

namespace Database\Factories;

use App\Domains\Respondents\Models\Answer;
use App\Domains\Respondents\Models\SurveyResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    protected $model = Answer::class;

    public function definition(): array
    {
        return [
            'response_id' => SurveyResponse::factory(),
            'snapshot_question_code' => fake()->bothify('Q-##'),
            'value_type' => 'short_text',
            'text_value' => fake()->sentence(),
            'is_not_applicable' => false,
            'created_at' => now(),
        ];
    }
}
