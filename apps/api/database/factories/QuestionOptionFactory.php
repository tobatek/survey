<?php

namespace Database\Factories;

use App\Domains\Questionnaires\Models\Question;
use App\Domains\Questionnaires\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionOption>
 */
class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'code' => 'O'.fake()->unique()->numerify('##'),
            'label' => fake()->words(2, true),
            'is_positive' => true,
            'sort_order' => 0,
        ];
    }
}
