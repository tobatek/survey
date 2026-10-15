<?php

namespace App\Domains\Respondents\Support;

use Illuminate\Support\Collection;

// FSD §11.3 -- versi runtime dari aturan struktural yang sudah divalidasi
// App\Domains\Questionnaires\Support\BranchGraphValidator saat finalize:
// section tanpa opsi branching selalu lanjut ke section berikutnya
// (sort_order); section yang punya opsi branching hanya lanjut ke section
// tujuan opsi yang benar-benar DIPILIH di draft (kalau belum dijawab, alur
// berhenti di situ -- section sesudahnya belum ditentukan). Bekerja di atas
// QuestionnaireSnapshot::structure_json (lihat
// FinalizeQuestionnaireAction::buildStructureJson untuk bentuk persisnya),
// bukan model Question/QuestionnaireSection live.
class DraftReachabilityCalculator
{
    /**
     * @param  array<string, mixed>  $structure  snapshot structure_json
     * @param  array<string, mixed>  $draftAnswers  question_code => value
     * @return array<int, int> id section yang reachable, terurut sort_order
     */
    public function reachableSectionIds(array $structure, array $draftAnswers): array
    {
        $sections = collect($structure['sections'] ?? [])->sortBy('sort_order')->values();

        $reachable = [];
        $currentIndex = $sections->isEmpty() ? null : 0;

        while ($currentIndex !== null) {
            $current = $sections->get($currentIndex);
            $reachable[] = $current['id'];

            $branchingQuestions = collect($current['questions'] ?? [])
                ->filter(fn (array $question) => $this->hasBranchingOption($question));

            if ($branchingQuestions->isEmpty()) {
                $nextIndex = $currentIndex + 1;
                $currentIndex = $sections->has($nextIndex) ? $nextIndex : null;

                continue;
            }

            $targetSectionId = $this->resolveChosenTarget($branchingQuestions, $draftAnswers);
            $currentIndex = $targetSectionId === null
                ? null
                : $sections->search(fn (array $section) => $section['id'] === $targetSectionId);

            if ($currentIndex === false) {
                $currentIndex = null;
            }
        }

        return $reachable;
    }

    /**
     * @param  array<string, mixed>  $structure
     * @param  array<string, mixed>  $draftAnswers
     * @return array<string, mixed>
     */
    public function stripUnreachableAnswers(array $structure, array $draftAnswers): array
    {
        $reachableSectionIds = $this->reachableSectionIds($structure, $draftAnswers);

        $reachableQuestionCodes = collect($structure['sections'] ?? [])
            ->filter(fn (array $section) => in_array($section['id'], $reachableSectionIds, true))
            ->flatMap(fn (array $section) => collect($section['questions'] ?? [])->pluck('code'))
            ->all();

        return array_intersect_key($draftAnswers, array_flip($reachableQuestionCodes));
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function hasBranchingOption(array $question): bool
    {
        return collect($question['options'] ?? [])->contains(fn (array $option) => $option['target_section_id'] !== null);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $branchingQuestions
     * @param  array<string, mixed>  $draftAnswers
     */
    private function resolveChosenTarget(Collection $branchingQuestions, array $draftAnswers): ?int
    {
        foreach ($branchingQuestions as $question) {
            $answer = $draftAnswers[$question['code']] ?? null;

            if (! is_string($answer)) {
                continue;
            }

            $option = collect($question['options'])->firstWhere('code', $answer);

            if ($option !== null && $option['target_section_id'] !== null) {
                return $option['target_section_id'];
            }
        }

        return null;
    }
}
