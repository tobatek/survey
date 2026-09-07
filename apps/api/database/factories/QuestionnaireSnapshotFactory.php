<?php

namespace Database\Factories;

use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\QuestionnaireSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuestionnaireSnapshot>
 */
class QuestionnaireSnapshotFactory extends Factory
{
    protected $model = QuestionnaireSnapshot::class;

    public function definition(): array
    {
        $structure = ['sections' => []];

        return [
            'questionnaire_id' => Questionnaire::factory(),
            'version' => 1,
            'structure_json' => $structure,
            'checksum' => hash('sha256', (string) Str::uuid()),
            'created_at' => now(),
        ];
    }
}
