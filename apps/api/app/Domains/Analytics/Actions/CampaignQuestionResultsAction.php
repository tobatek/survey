<?php

namespace App\Domains\Analytics\Actions;

use App\Domains\Analytics\Support\QuestionResultCalculator;
use App\Domains\Campaigns\Models\Campaign;

// FSD §13.4/§13.5 (Fase 5 slice 2) — hasil per pertanyaan, dibaca dari
// snapshot immutable campaign (bukan model Question/QuestionOption live).
class CampaignQuestionResultsAction
{
    public function __construct(private readonly QuestionResultCalculator $calculator) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Campaign $campaign): array
    {
        $structure = $campaign->snapshot?->structure_json ?? ['sections' => []];

        return [
            'sections' => collect($structure['sections'])->map(fn (array $section) => [
                'code' => $section['code'],
                'title' => $section['title'],
                'questions' => collect($section['questions'])->map(fn (array $question) => [
                    'code' => $question['code'],
                    'text' => $question['text'],
                    'type' => $question['type'],
                    'result' => $this->calculator->forQuestion($campaign->id, $question),
                ])->all(),
            ])->all(),
        ];
    }
}
