<?php

namespace Database\Factories;

use App\Domains\Questionnaires\Models\SurveyTemplateQuestion;
use App\Domains\Questionnaires\Models\SurveyTemplateSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyTemplateQuestion>
 */
class SurveyTemplateQuestionFactory extends Factory
{
    protected $model = SurveyTemplateQuestion::class;

    public function definition(): array
    {
        $section = SurveyTemplateSection::factory()->create();

        return [
            'template_id' => $section->template_id,
            'section_id' => $section->id,
            'code' => 'Q'.fake()->unique()->numerify('###'),
            'type' => 'short_text',
            'text' => fake()->sentence().'?',
            'required' => true,
            'sort_order' => 0,
        ];
    }
}
