<?php

namespace Database\Factories;

use App\Domains\Questionnaires\Models\Question;
use App\Domains\Questionnaires\Models\QuestionnaireSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        $section = QuestionnaireSection::factory()->create();

        return [
            'questionnaire_id' => $section->questionnaire_id,
            'section_id' => $section->id,
            'code' => 'Q'.fake()->unique()->numerify('###'),
            'type' => 'short_text',
            'text' => fake()->sentence().'?',
            'required' => true,
            'sort_order' => 0,
        ];
    }
}
