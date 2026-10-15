<?php

namespace App\Domains\Analytics\Support;

use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use App\Domains\Campaigns\Models\Campaign;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// FSD §14.1 (Fase 5 slice 5) -- hanya sheet Metadata/Response Summary/
// Question Results/Choice Distribution. Sheet Comments (fitur komentar
// belum ada, slice 6) dan Raw Data (di luar backlog slice 5) SENGAJA tidak
// dibuat sama sekali, bukan tab kosong.
//
// Karena data sumbernya (CampaignAnalyticsSummaryAction/
// CampaignQuestionResultsAction) sudah teragregasi -- bukan iterasi per
// respons mentah -- ukuran workbook dibatasi jumlah pertanyaan/opsi, bukan
// jumlah responden. Writer in-memory PhpSpreadsheet biasa cukup, tidak
// perlu mode streaming row-by-row.
//
// Threshold suppression (SegmentSizeGate) sudah diterapkan di kedua Action
// sumber sebelum array ini dibangun -- builder ini murni menulis apa yang
// diterima, tidak pernah query ulang, sehingga tidak ada jalur yang bisa
// lupa menerapkan suppression.
final class AnalyticsXlsxExportBuilder
{
    /**
     * @param  array<string, mixed>  $summary  hasil CampaignAnalyticsSummaryAction::execute()
     * @param  array<string, mixed>  $questions  hasil CampaignQuestionResultsAction::execute()
     */
    public function build(
        Campaign $campaign,
        string $questionnaireTitle,
        User $generatedBy,
        AnalyticsFilterDTO $filter,
        array $summary,
        array $questions,
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $this->writeMetadataSheet($spreadsheet, $campaign, $questionnaireTitle, $generatedBy, $filter, $summary);
        $this->writeResponseSummarySheet($spreadsheet, $summary);
        $this->writeQuestionResultsSheet($spreadsheet, $questions);
        $this->writeChoiceDistributionSheet($spreadsheet, $questions);

        return $spreadsheet;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function writeMetadataSheet(Spreadsheet $spreadsheet, Campaign $campaign, string $questionnaireTitle, User $generatedBy, AnalyticsFilterDTO $filter, array $summary): void
    {
        $sheet = $this->createSheet($spreadsheet, 'Metadata');

        $rows = [
            ['Campaign ID', $campaign->id],
            ['Judul Kuesioner', $questionnaireTitle],
            ['Dibuat pada', now()->toIso8601String()],
            ['Dibuat oleh', $generatedBy->display_name ?? $generatedBy->email],
            ['Ambang batas segmen (minimum_segment_size)', $campaign->minimum_segment_size],
            ['Data disensor (di bawah ambang batas)', ($summary['suppressed'] ?? false) ? 'Ya' : 'Tidak'],
        ];

        foreach ($filter->normalized() as $key => $value) {
            if ($value === null || $value === []) {
                continue;
            }

            $rows[] = ['Filter: '.$key, is_array($value) ? implode(', ', $value) : (string) $value];
        }

        $row = 1;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValueExplicit("A{$row}", $label, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$row}", (string) $value, DataType::TYPE_STRING);
            $row++;
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function writeResponseSummarySheet(Spreadsheet $spreadsheet, array $summary): void
    {
        $sheet = $this->createSheet($spreadsheet, 'Response Summary');

        if ($summary['suppressed'] ?? false) {
            $sheet->fromArray(['Data disembunyikan -- jumlah responden di bawah ambang batas minimum_segment_size'], null, 'A1');

            return;
        }

        $sheet->fromArray(['Status', 'Jumlah'], null, 'A1');
        $row = 2;
        foreach ($summary['funnel'] as $status => $count) {
            $sheet->setCellValueExplicit("A{$row}", $status, DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", $count);
            $row++;
        }

        $row++;
        $this->writeRateRow($sheet, $row++, 'Response rate', $summary['response_rate']);
        $this->writeRateRow($sheet, $row++, 'Completion rate', $summary['completion_rate']);
        $sheet->setCellValueExplicit("A{$row}", 'Total responses', DataType::TYPE_STRING);
        $sheet->setCellValue("B{$row}", $summary['total_responses']);
        $row++;
        $sheet->setCellValueExplicit("A{$row}", 'Respons terakhir', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("B{$row}", $summary['last_response_at'] ?? '-', DataType::TYPE_STRING);
    }

    /**
     * @param  array{numerator: int, denominator: int, value: float|null}  $rate
     */
    private function writeRateRow(Worksheet $sheet, int $row, string $label, array $rate): void
    {
        $sheet->setCellValueExplicit("A{$row}", $label, DataType::TYPE_STRING);
        $sheet->setCellValue("B{$row}", $rate['value']);
        $sheet->setCellValueExplicit("C{$row}", "n={$rate['numerator']}/{$rate['denominator']}", DataType::TYPE_STRING);
    }

    /**
     * @param  array<string, mixed>  $questions
     */
    private function writeQuestionResultsSheet(Spreadsheet $spreadsheet, array $questions): void
    {
        $sheet = $this->createSheet($spreadsheet, 'Question Results');
        $sheet->fromArray(['Section', 'Kode Pertanyaan', 'Teks Pertanyaan', 'Tipe', 'n', 'Ringkasan'], null, 'A1');

        $row = 2;
        foreach (QuestionResultRowFormatter::rows($questions) as $line) {
            $sheet->setCellValueExplicit("A{$row}", $line['section'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$row}", $line['code'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$row}", $line['text'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$row}", $line['type'], DataType::TYPE_STRING);
            $sheet->setCellValue("E{$row}", $line['n']);
            $sheet->setCellValueExplicit("F{$row}", $line['summary'], DataType::TYPE_STRING);
            $row++;
        }
    }

    /**
     * @param  array<string, mixed>  $questions
     */
    private function writeChoiceDistributionSheet(Spreadsheet $spreadsheet, array $questions): void
    {
        $sheet = $this->createSheet($spreadsheet, 'Choice Distribution');
        $sheet->fromArray(['Kode Pertanyaan', 'Baris Matrix', 'Kode Opsi', 'Label', 'n', 'Persentase'], null, 'A1');

        $row = 2;
        foreach ($questions['sections'] as $section) {
            foreach ($section['questions'] as $question) {
                $result = $question['result'];

                if ($result['suppressed'] ?? false) {
                    continue;
                }

                if (array_key_exists('rows', $result)) {
                    foreach ($result['rows'] as $matrixRow) {
                        if ($matrixRow['suppressed'] ?? false) {
                            continue;
                        }

                        foreach ($matrixRow['options'] as $option) {
                            $this->writeChoiceRow($sheet, $row++, $question['code'], $matrixRow['row_code'], $option);
                        }
                    }

                    continue;
                }

                if (! array_key_exists('options', $result)) {
                    continue;
                }

                foreach ($result['options'] as $option) {
                    $this->writeChoiceRow($sheet, $row++, $question['code'], null, $option);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $option
     */
    private function writeChoiceRow(Worksheet $sheet, int $row, string $questionCode, ?string $matrixRowCode, array $option): void
    {
        $sheet->setCellValueExplicit("A{$row}", $questionCode, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("B{$row}", $matrixRowCode ?? '', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("C{$row}", $option['option_code'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("D{$row}", $option['label'], DataType::TYPE_STRING);
        $sheet->setCellValue("E{$row}", $option['result']['numerator']);
        $sheet->setCellValue("F{$row}", $option['result']['value']);
    }

    private function createSheet(Spreadsheet $spreadsheet, string $title): Worksheet
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);

        return $sheet;
    }
}
