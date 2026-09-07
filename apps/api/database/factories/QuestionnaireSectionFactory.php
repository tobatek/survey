<?php

namespace Database\Factories;

use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\QuestionnaireSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionnaireSection>
 */
class QuestionnaireSectionFactory extends Factory
{
    protected $model = QuestionnaireSection::class;

    public function definition(): array
    {
        return [
            'questionnaire_id' => Questionnaire::factory(),
            'code' => 'S'.fake()->unique()->numerify('##'),
            'title' => fake()->words(3, true),
            'sort_order' => 0,
        ];
    }
}
