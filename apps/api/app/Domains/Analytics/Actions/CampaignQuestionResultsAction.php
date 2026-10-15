<?php

namespace App\Domains\Analytics\Actions;

use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use App\Domains\Analytics\Support\QuestionResultCalculator;
use App\Domains\Campaigns\Models\Campaign;

// FSD §13.4/§13.5 (Fase 5 slice 2-3) — hasil per pertanyaan, dibaca dari
// snapshot immutable campaign (bukan model Question/QuestionOption live).
// `question_search` (FSD §13.2) menyaring daftar pertanyaan SEBELUM dihitung
// -- pertanyaan yang tidak match tidak perlu query DB sama sekali.
class CampaignQuestionResultsAction
{
    public function __construct(private readonly QuestionResultCalculator $calculator) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Campaign $campaign, AnalyticsFilterDTO $filter): array
    {
        $structure = $campaign->snapshot?->structure_json ?? ['sections' => []];
        $minimumSegmentSize = $campaign->minimum_segment_size;

        return [
            'sections' => collect($structure['sections'])
                ->map(fn (array $section) => [
                    'code' => $section['code'],
                    'title' => $section['title'],
                    'questions' => collect($section['questions'])
                        ->filter(fn (array $question) => $this->matchesSearch($question, $filter->questionSearch))
                        ->map(fn (array $question) => [
                            'code' => $question['code'],
                            'text' => $question['text'],
                            'type' => $question['type'],
                            'result' => $this->calculator->forQuestion($campaign->id, $question, $filter, $minimumSegmentSize),
                        ])->values()->all(),
                ])
                ->filter(fn (array $section) => $section['questions'] !== [])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function matchesSearch(array $question, ?string $search): bool
    {
        if ($search === null || $search === '') {
            return true;
        }

        $needle = mb_strtolower($search);

        return str_contains(mb_strtolower($question['code']), $needle)
            || str_contains(mb_strtolower($question['text']), $needle);
    }
}
