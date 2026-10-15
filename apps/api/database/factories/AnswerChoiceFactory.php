<?php

namespace Database\Factories;

use App\Domains\Respondents\Models\Answer;
use App\Domains\Respondents\Models\AnswerChoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnswerChoice>
 */
class AnswerChoiceFactory extends Factory
{
    protected $model = AnswerChoice::class;

    public function definition(): array
    {
        return [
            'answer_id' => Answer::factory(),
            'option_code' => fake()->bothify('OPT-##'),
        ];
    }
}
