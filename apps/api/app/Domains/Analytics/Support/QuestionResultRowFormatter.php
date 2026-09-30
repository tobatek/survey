<?php

namespace App\Domains\Analytics\Support;

// FSD §14.1 (Fase 5 slice 6 bagian 2) -- diekstrak dari
// AnalyticsXlsxExportBuilder (slice 5) supaya satu sumber kebenaran untuk
// merender hasil "Question Results" jadi baris teks, dipakai builder XLSX
// dan CSV sekaligus (dua format, satu logika format).
final class QuestionResultRowFormatter
{
    /**
     * @param  array<string, mixed>  $questions  hasil CampaignQuestionResultsAction::execute()
     * @return list<array{section: string, code: string, text: string, type: string, n: int, summary: string}>
     */
    public static function rows(array $questions): array
    {
        $rows = [];

        foreach ($questions['sections'] as $section) {
            foreach ($section['questions'] as $question) {
                $result = $question['result'];

                $rows[] = [
                    'section' => $section['title'],
                    'code' => $question['code'],
                    'text' => $question['text'],
                    'type' => $question['type'],
                    'n' => self::n($result),
                    'summary' => self::summary($result),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function n(array $result): int
    {
        return $result['n'] ?? 0;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function summary(array $result): string
    {
        if ($result['suppressed'] ?? false) {
            return 'Disembunyikan (di bawah ambang batas)';
        }

        if (array_key_exists('rows', $result)) {
            return count($result['rows']).' baris matrix -- lihat sheet Choice Distribution';
        }

        $parts = [];
        if (array_key_exists('mean', $result) && $result['mean'] !== null) {
            $parts[] = sprintf('mean=%.4f', $result['mean']);
        }
        if (array_key_exists('median', $result) && $result['median'] !== null) {
            $parts[] = sprintf('median=%.4f', $result['median']);
        }
        if (array_key_exists('min', $result) && $result['min'] !== null) {
            $parts[] = sprintf('min=%.4f', $result['min']);
        }
        if (array_key_exists('max', $result) && $result['max'] !== null) {
            $parts[] = sprintf('max=%.4f', $result['max']);
        }
        if (isset($result['positive_rate']['value']) && $result['positive_rate']['value'] !== null) {
            $parts[] = sprintf('positive_rate=%.4f%%', $result['positive_rate']['value']);
        }
        if (array_key_exists('options', $result)) {
            $parts[] = 'lihat sheet Choice Distribution';
        }
        if (array_key_exists('buckets', $result)) {
            $parts[] = count($result['buckets']).' bucket tanggal -- lihat n';
        }

        return $parts === [] ? '' : implode('; ', $parts);
    }
}
