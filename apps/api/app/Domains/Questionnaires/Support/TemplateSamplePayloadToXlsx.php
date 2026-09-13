<?php

namespace App\Domains\Questionnaires\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

// Kebalikan XlsxTemplateWorkbookParser — menulis array payload bentuk
// envelope JSON (FSD §15.5) sebagai workbook XLSX (FSD §15.2) memakai sheet
// Template/Sections/Questions/Options/MatrixRows. Dipakai TemplateImportController
// untuk membangun sample.xlsx dari data yang sama dengan sample.json, supaya
// keduanya tidak pernah tidak-sinkron.
class TemplateSamplePayloadToXlsx
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function build(array $payload): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $template = $payload['template'];
        $this->writeSheet($spreadsheet, 'Template', ['template_code', 'title', 'description', 'category', 'audience_type', 'identity_mode', 'language'], [[
            $template['code'], $template['title'], $template['description'] ?? '',
            $template['category'], $template['audience_type'], $template['identity_mode'], $template['language'],
        ]]);

        $sectionRows = [];
        $questionRows = [];
        $optionRows = [];
        $matrixRows = [];

        foreach (array_values($payload['sections'] ?? []) as $sIndex => $section) {
            $sectionRows[] = [$section['code'], $section['title'], $section['description'] ?? '', $section['order'] ?? $sIndex];

            foreach (array_values($section['questions'] ?? []) as $qIndex => $question) {
                $questionRows[] = [
                    $section['code'], $question['code'], $question['text'], $question['help_text'] ?? '',
                    $question['type'], $question['required'] ?? false, $question['allow_not_applicable'] ?? false,
                    $question['min_value'] ?? '', $question['max_value'] ?? '', $question['order'] ?? $qIndex, $question['chart_type'] ?? '',
                ];

                foreach (array_values($question['options'] ?? []) as $oIndex => $option) {
                    $optionRows[] = [
                        $question['code'], $option['code'], $option['label'], $option['numeric_score'] ?? '',
                        $option['is_positive'] ?? '', $option['is_not_applicable'] ?? '', $option['order'] ?? $oIndex,
                        $option['target_section_code'] ?? '',
                    ];
                }

                foreach (array_values($question['matrix_rows'] ?? []) as $rIndex => $row) {
                    $matrixRows[] = [$question['code'], $row['code'], $row['label'], $row['order'] ?? $rIndex];
                }
            }
        }

        $this->writeSheet($spreadsheet, 'Sections', ['section_code', 'title', 'description', 'order'], $sectionRows);
        $this->writeSheet($spreadsheet, 'Questions', ['section_code', 'question_code', 'text', 'help_text', 'type', 'required', 'allow_not_applicable', 'min_value', 'max_value', 'order', 'chart_type'], $questionRows);
        $this->writeSheet($spreadsheet, 'Options', ['question_code', 'option_code', 'label', 'numeric_score', 'is_positive', 'is_not_applicable', 'order', 'target_section_code'], $optionRows);

        if ($matrixRows !== []) {
            $this->writeSheet($spreadsheet, 'MatrixRows', ['question_code', 'row_code', 'label', 'order'], $matrixRows);
        }

        return $spreadsheet;
    }

    /**
     * @param  string[]  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function writeSheet(Spreadsheet $spreadsheet, string $name, array $headers, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($name);
        $sheet->fromArray($headers, null, 'A1');

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }
    }
}
