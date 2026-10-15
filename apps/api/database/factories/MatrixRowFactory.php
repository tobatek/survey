<?php

namespace Database\Factories;

use App\Domains\Questionnaires\Models\MatrixRow;
use App\Domains\Questionnaires\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatrixRow>
 */
class MatrixRowFactory extends Factory
{
    protected $model = MatrixRow::class;

    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'code' => 'R'.fake()->unique()->numerify('##'),
            'label' => fake()->words(2, true),
            'sort_order' => 0,
        ];
    }
}
