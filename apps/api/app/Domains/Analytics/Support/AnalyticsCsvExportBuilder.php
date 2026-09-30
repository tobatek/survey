<?php

namespace App\Domains\Analytics\Support;

// FSD §14.1 (Fase 5 slice 6 bagian 2) -- CSV cuma boleh "satu dataset per
// permintaan" (beda dari XLSX yang punya 4 sheet sekaligus). Slice ini
// HANYA dataset Question Results (sama isinya dengan sheet "Question
// Results" AnalyticsXlsxExportBuilder, lewat QuestionResultRowFormatter
// yang sama) -- dataset lain menyusul nanti kalau dibutuhkan, TIDAK ada
// parameter pemilih dataset dulu (YAGNI, satu pilihan belum guna punya
// parameter).
//
// Tidak menyertakan baris Metadata terpisah seperti sheet Metadata di
// XLSX -- CSV murni satu tabel data sesuai FSD, konteks (campaign/filter)
// cukup dari nama file unduhan.
final class AnalyticsCsvExportBuilder
{
    private const HEADER = ['Section', 'Kode Pertanyaan', 'Teks Pertanyaan', 'Tipe', 'n', 'Ringkasan'];

    /**
     * @param  array<string, mixed>  $questions  hasil CampaignQuestionResultsAction::execute()
     */
    public function build(array $questions): string
    {
        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, self::HEADER);

        foreach (QuestionResultRowFormatter::rows($questions) as $line) {
            fputcsv($stream, [
                CsvFormulaInjectionGuard::escape($line['section']),
                CsvFormulaInjectionGuard::escape($line['code']),
                CsvFormulaInjectionGuard::escape($line['text']),
                CsvFormulaInjectionGuard::escape($line['type']),
                $line['n'],
                CsvFormulaInjectionGuard::escape($line['summary']),
            ]);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        // BOM UTF-8 -- supaya Excel Windows (target user utama myAMETA)
        // otomatis kenali encoding, bukan nebak ke codepage lokal (masalah
        // umum file CSV UTF-8 tanpa BOM dibuka Excel berbahasa Indonesia).
        return "\xEF\xBB\xBF".$content;
    }
}
