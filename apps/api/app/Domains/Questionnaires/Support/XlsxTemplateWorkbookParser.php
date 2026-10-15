<?php

namespace App\Domains\Questionnaires\Support;

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

// FSD §15.2 — konversi workbook XLSX (sheet Template/Sections/Questions/
// Options, opsional MatrixRows) menjadi array bentuk envelope JSON (FSD
// §15.5) yang sama persis dipakai TemplateImportValidator/
// CommitTemplateImportAction — kedua kelas itu tidak tahu (dan tidak perlu
// tahu) bahwa sumbernya XLSX. Hanya menangani FSD §15.3 tahap 1-2 (file
// bisa dibaca, sheet/kolom wajib ada); tahap 3-10 tetap tanggung jawab
// TemplateImportValidator.
class XlsxTemplateWorkbookParser
{
    private const REQUIRED_SHEETS = ['Template', 'Sections', 'Questions', 'Options'];

    private const TEMPLATE_REQUIRED = ['template_code', 'title', 'category', 'audience_type', 'identity_mode', 'language'];

    private const TEMPLATE_OPTIONAL = ['description'];

    private const SECTION_REQUIRED = ['section_code', 'title', 'order'];

    private const SECTION_OPTIONAL = ['description'];

    private const QUESTION_REQUIRED = ['section_code', 'question_code', 'text', 'type', 'required', 'allow_not_applicable', 'order'];

    private const QUESTION_OPTIONAL = ['help_text', 'min_value', 'max_value', 'chart_type'];

    private const OPTION_REQUIRED = ['question_code', 'option_code', 'label', 'order'];

    private const OPTION_OPTIONAL = ['numeric_score', 'is_positive', 'is_not_applicable', 'target_section_code'];

    private const MATRIX_ROW_REQUIRED = ['question_code', 'row_code', 'label', 'order'];

    /**
     * @return array{payload: ?array<string, mixed>, errors: array<int, array{code: string, message: string, path: string}>}
     */
    public function parse(string $binaryContent): array
    {
        $spreadsheet = $this->load($binaryContent);

        if ($spreadsheet === null) {
            return ['payload' => null, 'errors' => [
                ['code' => 'invalid_xlsx', 'message' => 'File bukan XLSX yang valid atau rusak.', 'path' => 'file'],
            ]];
        }

        $missingSheets = array_values(array_filter(
            self::REQUIRED_SHEETS,
            fn (string $name) => ! $spreadsheet->sheetNameExists($name),
        ));

        if ($missingSheets !== []) {
            return ['payload' => null, 'errors' => array_map(
                fn (string $name) => ['code' => 'missing_sheet', 'message' => "Sheet '{$name}' wajib ada.", 'path' => $name],
                $missingSheets,
            )];
        }

        [$templateRows, $templateErrors] = $this->readSheet($spreadsheet->getSheetByName('Template'), 'Template', self::TEMPLATE_REQUIRED, self::TEMPLATE_OPTIONAL);
        [$sectionRows, $sectionErrors] = $this->readSheet($spreadsheet->getSheetByName('Sections'), 'Sections', self::SECTION_REQUIRED, self::SECTION_OPTIONAL);
        [$questionRows, $questionErrors] = $this->readSheet($spreadsheet->getSheetByName('Questions'), 'Questions', self::QUESTION_REQUIRED, self::QUESTION_OPTIONAL);
        [$optionRows, $optionErrors] = $this->readSheet($spreadsheet->getSheetByName('Options'), 'Options', self::OPTION_REQUIRED, self::OPTION_OPTIONAL);

        $matrixRows = [];
        $matrixErrors = [];
        if ($spreadsheet->sheetNameExists('MatrixRows')) {
            [$matrixRows, $matrixErrors] = $this->readSheet($spreadsheet->getSheetByName('MatrixRows'), 'MatrixRows', self::MATRIX_ROW_REQUIRED, []);
        }

        $errors = [...$templateErrors, ...$sectionErrors, ...$questionErrors, ...$optionErrors, ...$matrixErrors];

        if ($errors !== []) {
            return ['payload' => null, 'errors' => $errors];
        }

        if ($templateRows === []) {
            return ['payload' => null, 'errors' => [
                ['code' => 'template_sheet_empty', 'message' => "Sheet 'Template' harus punya satu baris data.", 'path' => 'Template'],
            ]];
        }

        return ['payload' => $this->buildPayload($templateRows[0], $sectionRows, $questionRows, $optionRows, $matrixRows), 'errors' => []];
    }

    private function load(string $binaryContent): ?Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsximp_');

        try {
            file_put_contents($path, $binaryContent);
            $reader = new Xlsx;

            if (! $reader->canRead($path)) {
                return null;
            }

            $reader->setReadDataOnly(true);

            return $reader->load($path);
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param  string[]  $requiredHeaders
     * @param  string[]  $optionalHeaders
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array{code: string, message: string, path: string}>}
     */
    private function readSheet(Worksheet $sheet, string $sheetLabel, array $requiredHeaders, array $optionalHeaders): array
    {
        $table = $sheet->toArray(null, true, false, false);
        $headerRow = array_shift($table) ?? [];

        $columnByHeader = [];
        foreach ($headerRow as $index => $rawHeader) {
            $header = strtolower(trim((string) $rawHeader));
            if ($header !== '') {
                $columnByHeader[$header] = $index;
            }
        }

        $errors = [];
        foreach ($requiredHeaders as $header) {
            if (! isset($columnByHeader[$header])) {
                $errors[] = ['code' => 'missing_column', 'message' => "Kolom '{$header}' wajib ada di sheet '{$sheetLabel}'.", 'path' => "{$sheetLabel}.{$header}"];
            }
        }

        if ($errors !== []) {
            return [[], $errors];
        }

        $knownHeaders = [...$requiredHeaders, ...$optionalHeaders];
        $rows = [];

        foreach ($table as $rawRow) {
            $isBlank = true;
            $row = [];

            foreach ($knownHeaders as $header) {
                $value = isset($columnByHeader[$header]) ? ($rawRow[$columnByHeader[$header]] ?? null) : null;
                if ($value !== null && trim((string) $value) !== '') {
                    $isBlank = false;
                }
                $row[$header] = $value;
            }

            if (! $isBlank) {
                $rows[] = $row;
            }
        }

        return [$rows, []];
    }

    /**
     * @param  array<string, mixed>  $templateRow
     * @param  array<int, array<string, mixed>>  $sectionRows
     * @param  array<int, array<string, mixed>>  $questionRows
     * @param  array<int, array<string, mixed>>  $optionRows
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @return array<string, mixed>
     */
    private function buildPayload(array $templateRow, array $sectionRows, array $questionRows, array $optionRows, array $matrixRows): array
    {
        $questionsBySection = [];
        foreach ($questionRows as $row) {
            $questionsBySection[$this->str($row['section_code'])][] = $row;
        }

        $optionsByQuestion = [];
        foreach ($optionRows as $row) {
            $optionsByQuestion[$this->str($row['question_code'])][] = $row;
        }

        $matrixByQuestion = [];
        foreach ($matrixRows as $row) {
            $matrixByQuestion[$this->str($row['question_code'])][] = $row;
        }

        $sections = [];
        foreach ($sectionRows as $sectionRow) {
            $sectionCode = $this->str($sectionRow['section_code']);
            $questions = [];

            foreach ($questionsBySection[$sectionCode] ?? [] as $questionRow) {
                $questionCode = $this->str($questionRow['question_code']);
                $options = array_map(fn (array $r) => $this->buildOption($r), $optionsByQuestion[$questionCode] ?? []);
                $rows = array_map(fn (array $r) => $this->buildMatrixRow($r), $matrixByQuestion[$questionCode] ?? []);
                $questions[] = $this->buildQuestion($questionRow, $options, $rows);
            }

            $sections[] = $this->buildSection($sectionRow, $questions);
        }

        return [
            'schema_version' => '1.0',
            'template' => array_filter([
                'code' => $this->str($templateRow['template_code']),
                'title' => $this->str($templateRow['title']),
                'description' => $this->strOrNull($templateRow['description'] ?? null),
                'category' => $this->str($templateRow['category']),
                'audience_type' => $this->str($templateRow['audience_type']),
                'identity_mode' => $this->str($templateRow['identity_mode']),
                'language' => $this->str($templateRow['language']),
            ], fn ($v) => $v !== null),
            'sections' => $sections,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<string, mixed>
     */
    private function buildSection(array $row, array $questions): array
    {
        return array_filter([
            'code' => $this->str($row['section_code']),
            'title' => $this->str($row['title']),
            'description' => $this->strOrNull($row['description'] ?? null),
            'order' => $this->numOrNull($row['order'] ?? null),
            'questions' => $questions,
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, mixed>>  $options
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @return array<string, mixed>
     */
    private function buildQuestion(array $row, array $options, array $matrixRows): array
    {
        return array_filter([
            'code' => $this->str($row['question_code']),
            'text' => $this->str($row['text']),
            'type' => $this->str($row['type']),
            'help_text' => $this->strOrNull($row['help_text'] ?? null),
            'required' => $this->boolOrRaw($row['required'] ?? null),
            'allow_not_applicable' => $this->boolOrRaw($row['allow_not_applicable'] ?? null),
            'min_value' => $this->numOrNull($row['min_value'] ?? null),
            'max_value' => $this->numOrNull($row['max_value'] ?? null),
            'order' => $this->numOrNull($row['order'] ?? null),
            'chart_type' => $this->strOrNull($row['chart_type'] ?? null),
            'options' => $options,
            'matrix_rows' => $matrixRows,
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildOption(array $row): array
    {
        return array_filter([
            'code' => $this->str($row['option_code']),
            'label' => $this->str($row['label']),
            'numeric_score' => $this->numOrNull($row['numeric_score'] ?? null),
            'is_positive' => $this->boolOrRaw($row['is_positive'] ?? null),
            'is_not_applicable' => $this->boolOrRaw($row['is_not_applicable'] ?? null),
            'order' => $this->numOrNull($row['order'] ?? null),
            'target_section_code' => $this->strOrNull($row['target_section_code'] ?? null),
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildMatrixRow(array $row): array
    {
        return array_filter([
            'code' => $this->str($row['row_code']),
            'label' => $this->str($row['label']),
            'order' => $this->numOrNull($row['order'] ?? null),
        ], fn ($v) => $v !== null);
    }

    private function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function strOrNull(mixed $value): ?string
    {
        return $this->str($value);
    }

    private function numOrNull(mixed $value): int|float|null
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : null;
    }

    // Nilai boolean dikenal (true/false, 1/0, ya/tidak) dinormalisasi ke
    // bool asli; nilai lain diteruskan apa adanya (string mentah) supaya
    // TemplateImportValidator::validateEnums() (cek is_bool) tetap
    // menangkapnya sebagai question_invalid_boolean di tahap 4 — bukan
    // hilang senyap kalau di-null-kan di sini.
    private function boolOrRaw(mixed $value): mixed
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'true', '1', 'ya' => true,
            'false', '0', 'tidak' => false,
            default => $this->str($value),
        };
    }
}
