<?php

namespace App\Domains\Questionnaires\Support;

use App\Domains\Questionnaires\Models\Questionnaire;
use Illuminate\Support\Collection;

// FSD §7.4 — branching section-level: memilih sebuah opsi bisa mengarahkan
// responden ke section tujuan (bukan urutan default berikutnya). Validasi di
// sini murni struktural (dipanggil sebelum simpan/finalize); evaluasi runtime
// saat responden mengisi kuesioner adalah bagian Fase 4, di luar slice ini.
class BranchGraphValidator
{
    /**
     * @return array<int, array{code: string, message: string}> daftar error, kosong jika valid.
     */
    public function validate(Questionnaire $questionnaire): array
    {
        $errors = [];

        $sections = $questionnaire->sections;
        $sectionIds = $sections->pluck('id')->all();

        /** @var Collection $branchingOptions opsi yang punya target_section_id */
        $branchingOptions = $questionnaire->questions
            ->flatMap(fn ($question) => $question->options)
            ->filter(fn ($option) => $option->target_section_id !== null);

        foreach ($branchingOptions as $option) {
            if (! in_array($option->target_section_id, $sectionIds, true)) {
                $errors[] = [
                    'code' => 'branch_target_not_in_questionnaire',
                    'message' => "Opsi '{$option->code}' mengarah ke section di luar kuesioner ini.",
                ];

                continue;
            }

            $currentSectionId = $option->question->section_id;

            if ($option->target_section_id === $currentSectionId) {
                $errors[] = [
                    'code' => 'branch_target_is_self',
                    'message' => "Opsi '{$option->code}' tidak boleh mengarah ke section yang sama.",
                ];
            }
        }

        $errors = [...$errors, ...$this->detectCycles($sections, $branchingOptions)];
        $errors = [...$errors, ...$this->detectUnreachableSections($sections, $branchingOptions)];

        return $errors;
    }

    /**
     * Graf dianggap bersiklus jika, dengan mengikuti urutan default (sort_order)
     * DAN semua branch opsi sebagai edge, ada section yang bisa menjangkau
     * dirinya sendiri kembali.
     *
     * @return array<int, array{code: string, message: string}>
     */
    private function detectCycles(Collection $sections, Collection $branchingOptions): array
    {
        $edges = $this->buildEdges($sections, $branchingOptions);

        foreach ($sections as $start) {
            $visited = [];
            $stack = [$start->id];

            while ($stack !== []) {
                $current = array_pop($stack);

                if ($current === $start->id && $visited !== []) {
                    return [[
                        'code' => 'branch_cycle_detected',
                        'message' => 'Alur branching mengandung siklus tak berujung.',
                    ]];
                }

                if (isset($visited[$current])) {
                    continue;
                }

                $visited[$current] = true;

                foreach ($edges[$current] ?? [] as $next) {
                    $stack[] = $next;
                }
            }
        }

        return [];
    }

    /**
     * @return array<int, array{code: string, message: string}>
     */
    private function detectUnreachableSections(Collection $sections, Collection $branchingOptions): array
    {
        if ($sections->count() <= 1) {
            return [];
        }

        $edges = $this->buildEdges($sections, $branchingOptions);
        $first = $sections->first();

        $reachable = [$first->id => true];
        $queue = [$first->id];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($edges[$current] ?? [] as $next) {
                if (! isset($reachable[$next])) {
                    $reachable[$next] = true;
                    $queue[] = $next;
                }
            }
        }

        $errors = [];

        foreach ($sections as $section) {
            if (! isset($reachable[$section->id])) {
                $errors[] = [
                    'code' => 'section_unreachable',
                    'message' => "Section '{$section->code}' tidak bisa dijangkau dari alur manapun.",
                ];
            }
        }

        return $errors;
    }

    /**
     * Section tanpa opsi branching apa pun memakai urutan default (sort_order
     * berikutnya). Section yang punya minimal satu opsi branching dianggap
     * routing-nya sepenuhnya ditentukan oleh rule tsb — default TIDAK dipakai
     * lagi untuk section itu (FSD §7.4: "default dipakai saat tidak ada rule
     * yang cocok", jadi begitu ada rule, default bukan lagi jalur implisit).
     *
     * @return array<string, array<int, string>> peta section_id -> daftar section_id tujuan
     */
    private function buildEdges(Collection $sections, Collection $branchingOptions): array
    {
        $ordered = $sections->sortBy('sort_order')->values();
        $branchTargetsBySection = $branchingOptions
            ->filter(fn ($option) => $option->target_section_id !== null)
            ->groupBy(fn ($option) => $option->question->section_id);

        $edges = [];

        foreach ($ordered as $index => $section) {
            if ($branchTargetsBySection->has($section->id)) {
                $edges[$section->id] = $branchTargetsBySection->get($section->id)
                    ->pluck('target_section_id')
                    ->unique()
                    ->values()
                    ->all();

                continue;
            }

            $next = $ordered->get($index + 1);
            $edges[$section->id] = $next !== null ? [$next->id] : [];
        }

        return $edges;
    }
}
