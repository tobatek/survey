<?php

namespace Database\Factories;

use App\Domains\Questionnaires\Models\SurveyTemplateMatrixRow;
use App\Domains\Questionnaires\Models\SurveyTemplateQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyTemplateMatrixRow>
 */
class SurveyTemplateMatrixRowFactory extends Factory
{
    protected $model = SurveyTemplateMatrixRow::class;

    public function definition(): array
    {
        return [
            'question_id' => SurveyTemplateQuestion::factory(),
            'code' => 'R'.fake()->unique()->numerify('##'),
            'label' => fake()->words(2, true),
            'sort_order' => 0,
        ];
    }
}
