<?php

namespace App\Domains\Analytics\Support;

use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// FSD §13.4/§13.5 (Fase 5 slice 2-3) — menghitung hasil per pertanyaan dari
// data mentah `answers`/`answer_choices`, di-scope ke satu campaign lewat
// join `responses.campaign_id`, ditambah filter segmen (`SegmentFilterScope`)
// dan threshold suppression per-pertanyaan (`SegmentSizeGate`, slice 3).
// Sumber definisi pertanyaan/opsi SELALU snapshot immutable (array dari
// structure_json), bukan model live Question/QuestionOption -- pola sama
// DraftReachabilityCalculator (Fase 4) dan AnswerShapeValidator. Query di
// sini query langsung (belum materialized, itu Fase 5 slice 4).
final class QuestionResultCalculator
{
    /**
     * @param  array<string, mixed>  $question  satu entri `sections[].questions[]` dari structure_json
     * @return array<string, mixed>
     */
    public function forQuestion(string $campaignId, array $question, AnalyticsFilterDTO $filter, int $minimumSegmentSize): array
    {
        return match ($question['type']) {
            'single_choice', 'dropdown' => $this->choiceResult($campaignId, $question, $filter, $minimumSegmentSize, withScore: false),
            'likert' => $this->choiceResult($campaignId, $question, $filter, $minimumSegmentSize, withScore: true),
            'multiple_choice' => $this->multipleChoiceResult($campaignId, $question, $filter, $minimumSegmentSize),
            'matrix' => $this->matrixResult($campaignId, $question, $filter, $minimumSegmentSize),
            'number' => $this->numberResult($campaignId, $question, $filter, $minimumSegmentSize),
            'date' => $this->dateResult($campaignId, $question, $filter, $minimumSegmentSize),
            'short_text', 'long_text' => $this->textResult($campaignId, $question, $filter, $minimumSegmentSize),
            default => throw new RuntimeException("Tipe pertanyaan tidak dikenal: {$question['type']}"),
        };
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function choiceResult(string $campaignId, array $question, AnalyticsFilterDTO $filter, int $minimumSegmentSize, bool $withScore): array
    {
        $validAnswerCount = $this->answerQuery($campaignId, $question['code'], $filter)
            ->where('answers.is_not_applicable', false)
            ->count();

        if (SegmentSizeGate::isSuppressed($validAnswerCount, $minimumSegmentSize)) {
            return ['suppressed' => true, 'n' => $validAnswerCount];
        }

        $positiveOptionCodes = collect($question['options'])
            ->filter(fn ($option) => (bool) ($option['is_positive'] ?? false))
            ->pluck('code')
            ->all();

        $options = collect($question['options'])->map(function (array $option) use ($campaignId, $question, $filter, $validAnswerCount) {
            $optionCount = $this->choiceCountQuery($campaignId, $question['code'], $option['code'], $filter)->count();

            return [
                'option_code' => $option['code'],
                'label' => $option['label'],
                'result' => AnalyticsFormulas::singleChoicePercentage($optionCount, $validAnswerCount),
            ];
        })->all();

        $result = [
            'n' => $validAnswerCount,
            'options' => $options,
        ];

        if ($withScore) {
            $scores = $this->scoreQuery($campaignId, $question['code'], $filter)->pluck('answer_choices.numeric_score')
                ->map(fn ($score) => (float) $score)
                ->all();

            $positiveCount = $positiveOptionCodes === [] ? 0 : collect($positiveOptionCodes)
                ->sum(fn ($code) => $this->choiceCountQuery($campaignId, $question['code'], $code, $filter)->count());

            $result['mean'] = AnalyticsFormulas::mean($scores);
            $result['median'] = AnalyticsFormulas::median($scores);
            $result['positive_rate'] = $positiveOptionCodes === []
                ? null
                : AnalyticsFormulas::positiveRate($positiveCount, $validAnswerCount);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function multipleChoiceResult(string $campaignId, array $question, AnalyticsFilterDTO $filter, int $minimumSegmentSize): array
    {
        $respondentsWithValidAnswer = $this->answerQuery($campaignId, $question['code'], $filter)
            ->where('answers.is_not_applicable', false)
            ->count();

        if (SegmentSizeGate::isSuppressed($respondentsWithValidAnswer, $minimumSegmentSize)) {
            return ['suppressed' => true, 'n' => $respondentsWithValidAnswer];
        }

        $options = collect($question['options'])->map(function (array $option) use ($campaignId, $question, $filter, $respondentsWithValidAnswer) {
            $respondentsSelecting = $this->choiceCountQuery($campaignId, $question['code'], $option['code'], $filter)
                ->distinct('answer_choices.answer_id')
                ->count('answer_choices.answer_id');

            return [
                'option_code' => $option['code'],
                'label' => $option['label'],
                'result' => AnalyticsFormulas::multipleChoicePercentage($respondentsSelecting, $respondentsWithValidAnswer),
            ];
        })->all();

        return [
            'n' => $respondentsWithValidAnswer,
            'options' => $options,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function matrixResult(string $campaignId, array $question, AnalyticsFilterDTO $filter, int $minimumSegmentSize): array
    {
        $positiveOptionCodes = collect($question['options'])
            ->filter(fn ($option) => (bool) ($option['is_positive'] ?? false))
            ->pluck('code')
            ->all();

        // Threshold dicek PER BARIS (bukan per pertanyaan) -- satu matrix
        // bisa punya baris dengan n jauh berbeda (mis. baris "opsional")
        // walau filter yang dipakai sama.
        $rows = collect($question['matrix_rows'])->map(function (array $row) use ($campaignId, $question, $filter, $minimumSegmentSize, $positiveOptionCodes) {
            $validAnswerCount = $this->answerQuery($campaignId, $question['code'], $filter, $row['code'])
                ->where('answers.is_not_applicable', false)
                ->count();

            if (SegmentSizeGate::isSuppressed($validAnswerCount, $minimumSegmentSize)) {
                return ['row_code' => $row['code'], 'label' => $row['label'], 'suppressed' => true, 'n' => $validAnswerCount];
            }

            $options = collect($question['options'])->map(function (array $option) use ($campaignId, $question, $filter, $row, $validAnswerCount) {
                $optionCount = $this->choiceCountQuery($campaignId, $question['code'], $option['code'], $filter, $row['code'])->count();

                return [
                    'option_code' => $option['code'],
                    'label' => $option['label'],
                    'result' => AnalyticsFormulas::singleChoicePercentage($optionCount, $validAnswerCount),
                ];
            })->all();

            $scores = $this->scoreQuery($campaignId, $question['code'], $filter, $row['code'])
                ->pluck('answer_choices.numeric_score')
                ->map(fn ($score) => (float) $score)
                ->all();

            $positiveCount = $positiveOptionCodes === [] ? 0 : collect($positiveOptionCodes)
                ->sum(fn ($code) => $this->choiceCountQuery($campaignId, $question['code'], $code, $filter, $row['code'])->count());

            return [
                'row_code' => $row['code'],
                'label' => $row['label'],
                'n' => $validAnswerCount,
                'mean' => AnalyticsFormulas::mean($scores),
                'median' => AnalyticsFormulas::median($scores),
                'positive_rate' => $positiveOptionCodes === []
                    ? null
                    : AnalyticsFormulas::positiveRate($positiveCount, $validAnswerCount),
                'options' => $options,
            ];
        })->all();

        return ['rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function numberResult(string $campaignId, array $question, AnalyticsFilterDTO $filter, int $minimumSegmentSize): array
    {
        $values = $this->answerQuery($campaignId, $question['code'], $filter)
            ->where('answers.is_not_applicable', false)
            ->whereNotNull('answers.numeric_value')
            ->pluck('answers.numeric_value')
            ->map(fn ($value) => (float) $value)
            ->all();

        if (SegmentSizeGate::isSuppressed(count($values), $minimumSegmentSize)) {
            return ['suppressed' => true, 'n' => count($values)];
        }

        return [
            'n' => count($values),
            'mean' => AnalyticsFormulas::mean($values),
            'median' => AnalyticsFormulas::median($values),
            'min' => $values === [] ? null : min($values),
            'max' => $values === [] ? null : max($values),
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function dateResult(string $campaignId, array $question, AnalyticsFilterDTO $filter, int $minimumSegmentSize): array
    {
        $buckets = $this->answerQuery($campaignId, $question['code'], $filter)
            ->where('answers.is_not_applicable', false)
            ->whereNotNull('answers.date_value')
            ->selectRaw('answers.date_value as date, count(*) as total')
            ->groupBy('answers.date_value')
            ->orderBy('answers.date_value')
            ->get()
            // Format tanggal dinormalisasi eksplisit ke Y-m-d -- kolom
            // `date` SQLite (dipakai test suite) menyimpan sebagai string
            // apa adanya, beda dari Postgres yang selalu date murni.
            ->map(fn ($row) => ['date' => Carbon::parse($row->date)->toDateString(), 'count' => (int) $row->total])
            ->all();

        $n = array_sum(array_column($buckets, 'count'));

        if (SegmentSizeGate::isSuppressed($n, $minimumSegmentSize)) {
            return ['suppressed' => true, 'n' => $n];
        }

        return [
            'n' => $n,
            'buckets' => $buckets,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function textResult(string $campaignId, array $question, AnalyticsFilterDTO $filter, int $minimumSegmentSize): array
    {
        // Isi jawaban esai TIDAK dikembalikan di sini -- itu domain endpoint
        // `.../comments` (role-gated, paginated, di luar scope slice 2).
        $n = $this->answerQuery($campaignId, $question['code'], $filter)
            ->where('answers.is_not_applicable', false)
            ->count();

        if (SegmentSizeGate::isSuppressed($n, $minimumSegmentSize)) {
            return ['suppressed' => true, 'n' => $n];
        }

        return ['n' => $n];
    }

    private function answerQuery(string $campaignId, string $questionCode, AnalyticsFilterDTO $filter, ?string $matrixRowCode = null): Builder
    {
        $query = DB::table('answers')
            ->join('responses', 'responses.id', '=', 'answers.response_id')
            ->where('responses.campaign_id', $campaignId)
            ->where('answers.snapshot_question_code', $questionCode);

        if ($matrixRowCode !== null) {
            $query->where('answers.matrix_row_code', $matrixRowCode);
        }

        SegmentFilterScope::applyToResponseScope($query, $filter);

        return $query;
    }

    private function choiceCountQuery(string $campaignId, string $questionCode, string $optionCode, AnalyticsFilterDTO $filter, ?string $matrixRowCode = null): Builder
    {
        $query = DB::table('answer_choices')
            ->join('answers', 'answers.id', '=', 'answer_choices.answer_id')
            ->join('responses', 'responses.id', '=', 'answers.response_id')
            ->where('responses.campaign_id', $campaignId)
            ->where('answers.snapshot_question_code', $questionCode)
            ->where('answers.is_not_applicable', false)
            ->where('answer_choices.option_code', $optionCode);

        if ($matrixRowCode !== null) {
            $query->where('answers.matrix_row_code', $matrixRowCode);
        }

        SegmentFilterScope::applyToResponseScope($query, $filter);

        return $query;
    }

    private function scoreQuery(string $campaignId, string $questionCode, AnalyticsFilterDTO $filter, ?string $matrixRowCode = null): Builder
    {
        $query = DB::table('answer_choices')
            ->join('answers', 'answers.id', '=', 'answer_choices.answer_id')
            ->join('responses', 'responses.id', '=', 'answers.response_id')
            ->where('responses.campaign_id', $campaignId)
            ->where('answers.snapshot_question_code', $questionCode)
            ->where('answers.is_not_applicable', false)
            ->whereNotNull('answer_choices.numeric_score');

        if ($matrixRowCode !== null) {
            $query->where('answers.matrix_row_code', $matrixRowCode);
        }

        SegmentFilterScope::applyToResponseScope($query, $filter);

        return $query;
    }
}
