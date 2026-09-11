<?php

namespace Database\Factories;

use App\Domains\Questionnaires\Models\SurveyTemplate;
use App\Domains\Questionnaires\Models\SurveyTemplateSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyTemplateSection>
 */
class SurveyTemplateSectionFactory extends Factory
{
    protected $model = SurveyTemplateSection::class;

    public function definition(): array
    {
        return [
            'template_id' => SurveyTemplate::factory(),
            'code' => 'S'.fake()->unique()->numerify('##'),
            'title' => fake()->words(3, true),
            'sort_order' => 0,
        ];
    }
}
