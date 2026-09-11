<?php

namespace Database\Factories;

use App\Domains\Questionnaires\Models\SurveyTemplateOption;
use App\Domains\Questionnaires\Models\SurveyTemplateQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyTemplateOption>
 */
class SurveyTemplateOptionFactory extends Factory
{
    protected $model = SurveyTemplateOption::class;

    public function definition(): array
    {
        return [
            'question_id' => SurveyTemplateQuestion::factory(),
            'code' => 'O'.fake()->unique()->numerify('##'),
            'label' => fake()->words(2, true),
            'is_positive' => true,
            'sort_order' => 0,
        ];
    }
}
