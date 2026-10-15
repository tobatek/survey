<?php

namespace App\Domains\Analytics\Actions;

use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use App\Domains\Analytics\Exceptions\CommentQuestionNotFoundException;
use App\Domains\Analytics\Support\SegmentFilterScope;
use App\Domains\Analytics\Support\SegmentSizeGate;
use App\Domains\Campaigns\Models\Campaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// FSD §17.6/§12.2/§21 (Fase 5 slice 6 bagian 1) -- isi jawaban esai, satu
// pertanyaan per request (bukan gabungan semua pertanyaan teks sekaligus,
// lihat FSD §13.5 fallback "paginated comments" per pertanyaan). Identitas
// responden TIDAK PERNAH disertakan (keputusan produk, dikonfirmasi user) --
// hanya text_value + segment_snapshot_json (field non-identitas yang sama
// dipakai SegmentFilterScope) + submitted_at.
//
// Threshold suppression diterapkan di LEVEL LISTING (bukan per-baris seperti
// QuestionResultCalculator): kalau jumlah jawaban yang cocok (setelah filter
// segmen + search) di bawah minimum_segment_size, SELURUH listing disensor
// -- teks bebas dari kelompok sangat kecil punya risiko re-identifikasi
// lebih tinggi daripada sekadar angka agregat.
class CampaignCommentsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Campaign $campaign, string $questionCode, AnalyticsFilterDTO $filter, ?string $search, int $perPage): array
    {
        $this->assertIsTextQuestion($campaign, $questionCode);

        $query = DB::table('answers')
            ->join('responses', 'responses.id', '=', 'answers.response_id')
            ->where('responses.campaign_id', $campaign->id)
            ->where('answers.snapshot_question_code', $questionCode)
            ->where('answers.is_not_applicable', false)
            ->whereNotNull('answers.text_value');

        SegmentFilterScope::applyToResponseScope($query, $filter);

        if ($search !== null && $search !== '') {
            $query->where('answers.text_value', 'like', '%'.$search.'%');
        }

        $matchedCount = (clone $query)->count();

        if (SegmentSizeGate::isSuppressed($matchedCount, $campaign->minimum_segment_size)) {
            return [
                'suppressed' => true,
                'minimum_segment_size' => $campaign->minimum_segment_size,
            ];
        }

        $page = $query
            ->select(['answers.id', 'answers.text_value', 'responses.segment_snapshot_json', 'responses.submitted_at'])
            ->orderByDesc('responses.submitted_at')
            ->paginate($perPage);

        return [
            'data' => collect($page->items())->map(fn ($row) => [
                'id' => $row->id,
                'question_code' => $questionCode,
                'text' => $row->text_value,
                'submitted_at' => $row->submitted_at ? Carbon::parse($row->submitted_at)->toIso8601String() : null,
                'segment' => json_decode($row->segment_snapshot_json ?? '{}', true) ?? [],
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ];
    }

    private function assertIsTextQuestion(Campaign $campaign, string $questionCode): void
    {
        $structure = $campaign->snapshot?->structure_json ?? ['sections' => []];

        foreach ($structure['sections'] as $section) {
            foreach ($section['questions'] as $question) {
                if ($question['code'] === $questionCode) {
                    if (! in_array($question['type'], ['short_text', 'long_text'], true)) {
                        throw new CommentQuestionNotFoundException($questionCode);
                    }

                    return;
                }
            }
        }

        throw new CommentQuestionNotFoundException($questionCode);
    }
}
