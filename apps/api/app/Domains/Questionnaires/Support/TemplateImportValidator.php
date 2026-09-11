<?php

namespace App\Domains\Questionnaires\Support;

use App\Domains\Questionnaires\Models\Question;
use App\Domains\Questionnaires\Models\SurveyTemplate;

// FSD §15.3 — 10 tahap validasi import template (slice JSON). Bentuk envelope
// yang diterima (FSD §15.5, disesuaikan jadi bersarang per section untuk
// format JSON — lihat komentar method `validate()`):
//
// {
//   "schema_version": "1.0",
//   "template": {code,title,description?,category,audience_type,identity_mode,language},
//   "sections": [{
//     code, title, description?, order?,
//     "questions": [{
//       code, text, type, help_text?, required?, allow_not_applicable?,
//       min_value?, max_value?, order?, chart_type?,
//       "options": [{code,label,numeric_score?,is_positive?,is_not_applicable?,order?,target_section_code?}],
//       "matrix_rows": [{code,label,order?}]
//     }]
//   }]
// }
//
// Setiap masalah berbentuk {code, message, path} — `path` menunjuk lokasi
// (mis. "sections[1].questions[0].code") supaya UI bisa menandai baris/kolom
// bermasalah (FSD §15.4 "preview error per baris/kolom").
class TemplateImportValidator
{
    private const SUPPORTED_AUDIENCE_TYPES = ['student', 'employee'];

    private const SUPPORTED_IDENTITY_MODES = ['anonymous', 'identified', 'verified_anonymous'];

    /**
     * @return array{errors: array<int, array{code: string, message: string, path: string}>, warnings: array<int, array{code: string, message: string, path: string}>, preview: array{template_code: ?string, sections: int, questions: int, options: int}}
     */
    public function validate(array $payload, string $tenantId): array
    {
        $errors = [];
        $warnings = [];

        // Tahap 1: kehadiran struktur envelope minimal.
        if (! isset($payload['template']) || ! is_array($payload['template'])) {
            $errors[] = $this->issue('envelope_missing_template', "Field 'template' wajib ada dan berbentuk objek.", 'template');
        }

        if (! isset($payload['sections']) || ! is_array($payload['sections'])) {
            $errors[] = $this->issue('envelope_missing_sections', "Field 'sections' wajib ada dan berbentuk array.", 'sections');
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'warnings' => $warnings, 'preview' => $this->emptyPreview()];
        }

        // Tahap 2: schema_version — mayor tidak dikenal ditolak (FSD §15.5).
        $schemaVersion = (string) ($payload['schema_version'] ?? '');
        $major = explode('.', $schemaVersion)[0] ?? '';

        if ($major !== '1') {
            $errors[] = $this->issue('unsupported_schema_version', "schema_version '{$schemaVersion}' tidak dikenal (mayor yang didukung: 1).", 'schema_version');

            return ['errors' => $errors, 'warnings' => $warnings, 'preview' => $this->emptyPreview()];
        }

        $template = $payload['template'];
        $sections = array_values($payload['sections']);

        // Tahap 3: kolom wajib.
        $errors = [...$errors, ...$this->validateRequiredFields($template, $sections)];

        // Tahap 4: tipe data/enum.
        $errors = [...$errors, ...$this->validateEnums($template, $sections)];

        // Tahap 5: kode duplikat (section/question/option/matrix row).
        $errors = [...$errors, ...$this->validateDuplicateCodes($sections)];

        // Tahap 6: cross-reference branching (target_section_code harus ada & bukan diri sendiri).
        $errors = [...$errors, ...$this->validateBranchTargets($sections)];

        // Tahap 7: aturan per tipe pertanyaan (opsi/matrix rows wajib sesuai tipe).
        $errors = [...$errors, ...$this->validateQuestionTypeRules($sections)];

        // Tahap 8: branch graph — cycle & unreachable section.
        $errors = [...$errors, ...$this->validateBranchGraph($sections)];

        // Tahap 9: konsistensi skor (soft — dilaporkan sebagai warning).
        $warnings = [...$warnings, ...$this->validateScoringConsistency($sections)];

        // Tahap 10: kode template belum dipakai di tenant ini + susun preview.
        $templateCode = is_string($template['code'] ?? null) ? $template['code'] : null;

        if ($templateCode !== null && SurveyTemplate::query()->where('tenant_id', $tenantId)->where('code', $templateCode)->exists()) {
            $errors[] = $this->issue('template_code_taken', "Kode template '{$templateCode}' sudah dipakai.", 'template.code');
        }

        $preview = $this->buildPreview($templateCode, $sections);

        return ['errors' => $errors, 'warnings' => $warnings, 'preview' => $preview];
    }

    /**
     * @return array<int, array{code: string, message: string, path: string}>
     */
    private function validateRequiredFields(array $template, array $sections): array
    {
        $errors = [];

        foreach (['code', 'title', 'category', 'audience_type', 'identity_mode', 'language'] as $field) {
            if (! isset($template[$field]) || trim((string) $template[$field]) === '') {
                $errors[] = $this->issue('template_field_required', "Field template '{$field}' wajib diisi.", "template.{$field}");
            }
        }

        foreach ($sections as $sIndex => $section) {
            $sectionPath = "sections[{$sIndex}]";

            foreach (['code', 'title'] as $field) {
                if (! isset($section[$field]) || trim((string) $section[$field]) === '') {
                    $errors[] = $this->issue('section_field_required', "Field section '{$field}' wajib diisi.", "{$sectionPath}.{$field}");
                }
            }

            foreach (array_values($section['questions'] ?? []) as $qIndex => $question) {
                $questionPath = "{$sectionPath}.questions[{$qIndex}]";

                foreach (['code', 'text', 'type'] as $field) {
                    if (! isset($question[$field]) || trim((string) $question[$field]) === '') {
                        $errors[] = $this->issue('question_field_required', "Field pertanyaan '{$field}' wajib diisi.", "{$questionPath}.{$field}");
                    }
                }

                foreach (array_values($question['options'] ?? []) as $oIndex => $option) {
                    $optionPath = "{$questionPath}.options[{$oIndex}]";

                    foreach (['code', 'label'] as $field) {
                        if (! isset($option[$field]) || trim((string) $option[$field]) === '') {
                            $errors[] = $this->issue('option_field_required', "Field opsi '{$field}' wajib diisi.", "{$optionPath}.{$field}");
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array{code: string, message: string, path: string}>
     */
    private function validateEnums(array $template, array $sections): array
    {
        $errors = [];

        if (isset($template['audience_type']) && ! in_array($template['audience_type'], self::SUPPORTED_AUDIENCE_TYPES, true)) {
            $errors[] = $this->issue('template_invalid_audience_type', "audience_type '{$template['audience_type']}' tidak dikenal.", 'template.audience_type');
        }

        if (isset($template['identity_mode']) && ! in_array($template['identity_mode'], self::SUPPORTED_IDENTITY_MODES, true)) {
            $errors[] = $this->issue('template_invalid_identity_mode', "identity_mode '{$template['identity_mode']}' tidak dikenal.", 'template.identity_mode');
        }

        foreach ($sections as $sIndex => $section) {
            foreach (array_values($section['questions'] ?? []) as $qIndex => $question) {
                $questionPath = "sections[{$sIndex}].questions[{$qIndex}]";

                if (isset($question['type']) && ! in_array($question['type'], Question::TYPES, true)) {
                    $errors[] = $this->issue('question_invalid_type', "Tipe pertanyaan '{$question['type']}' tidak dikenal.", "{$questionPath}.type");
                }

                foreach (['required', 'allow_not_applicable'] as $field) {
                    if (isset($question[$field]) && ! is_bool($question[$field])) {
                        $errors[] = $this->issue('question_invalid_boolean', "Field '{$field}' harus boolean.", "{$questionPath}.{$field}");
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array{code: string, message: string, path: string}>
     */
    private function validateDuplicateCodes(array $sections): array
    {
        $errors = [];
        $seenSectionCodes = [];
        $seenQuestionCodes = [];

        foreach ($sections as $sIndex => $section) {
            $sectionCode = $section['code'] ?? null;

            if ($sectionCode !== null) {
                if (isset($seenSectionCodes[$sectionCode])) {
                    $errors[] = $this->issue('duplicate_section_code', "Kode section '{$sectionCode}' duplikat.", "sections[{$sIndex}].code");
                }
                $seenSectionCodes[$sectionCode] = true;
            }

            foreach (array_values($section['questions'] ?? []) as $qIndex => $question) {
                $questionPath = "sections[{$sIndex}].questions[{$qIndex}]";
                $questionCode = $question['code'] ?? null;

                if ($questionCode !== null) {
                    if (isset($seenQuestionCodes[$questionCode])) {
                        $errors[] = $this->issue('duplicate_question_code', "Kode pertanyaan '{$questionCode}' duplikat.", "{$questionPath}.code");
                    }
                    $seenQuestionCodes[$questionCode] = true;
                }

                $seenOptionCodes = [];
                foreach (array_values($question['options'] ?? []) as $oIndex => $option) {
                    $optionCode = $option['code'] ?? null;
                    if ($optionCode !== null) {
                        if (isset($seenOptionCodes[$optionCode])) {
                            $errors[] = $this->issue('duplicate_option_code', "Kode opsi '{$optionCode}' duplikat pada pertanyaan '{$questionCode}'.", "{$questionPath}.options[{$oIndex}].code");
                        }
                        $seenOptionCodes[$optionCode] = true;
                    }
                }

                $seenRowCodes = [];
                foreach (array_values($question['matrix_rows'] ?? []) as $rIndex => $row) {
                    $rowCode = $row['code'] ?? null;
                    if ($rowCode !== null) {
                        if (isset($seenRowCodes[$rowCode])) {
                            $errors[] = $this->issue('duplicate_matrix_row_code', "Kode baris matrix '{$rowCode}' duplikat pada pertanyaan '{$questionCode}'.", "{$questionPath}.matrix_rows[{$rIndex}].code");
                        }
                        $seenRowCodes[$rowCode] = true;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array{code: string, message: string, path: string}>
     */
    private function validateBranchTargets(array $sections): array
    {
        $errors = [];
        $sectionCodes = array_column($sections, 'code');

        foreach ($sections as $sIndex => $section) {
            foreach (array_values($section['questions'] ?? []) as $qIndex => $question) {
                foreach (array_values($question['options'] ?? []) as $oIndex => $option) {
                    $target = $option['target_section_code'] ?? null;

                    if ($target === null) {
                        continue;
                    }

                    $optionPath = "sections[{$sIndex}].questions[{$qIndex}].options[{$oIndex}].target_section_code";

                    if (! in_array($target, $sectionCodes, true)) {
                        $errors[] = $this->issue('branch_target_not_found', "Opsi '{$option['code']}' mengarah ke section '{$target}' yang tidak ada.", $optionPath);

                        continue;
                    }

                    if ($target === ($section['code'] ?? null)) {
                        $errors[] = $this->issue('branch_target_is_self', "Opsi '{$option['code']}' tidak boleh mengarah ke section yang sama.", $optionPath);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array{code: string, message: string, path: string}>
     */
    private function validateQuestionTypeRules(array $sections): array
    {
        $errors = [];

        foreach ($sections as $sIndex => $section) {
            foreach (array_values($section['questions'] ?? []) as $qIndex => $question) {
                $type = $question['type'] ?? null;

                if (! in_array($type, Question::TYPES, true)) {
                    continue;
                }

                $questionPath = "sections[{$sIndex}].questions[{$qIndex}]";
                $options = array_values($question['options'] ?? []);
                $matrixRows = array_values($question['matrix_rows'] ?? []);
                $needsOptions = in_array($type, [...Question::CHOICE_TYPES, 'multiple_choice'], true) || $type === 'matrix';

                if ($needsOptions && $options === []) {
                    $errors[] = $this->issue('question_has_no_options', "Pertanyaan '{$question['code']}' butuh minimal satu opsi.", "{$questionPath}.options");
                }

                if ($type === 'matrix' && $matrixRows === []) {
                    $errors[] = $this->issue('matrix_has_no_rows', "Pertanyaan matrix '{$question['code']}' butuh minimal satu baris.", "{$questionPath}.matrix_rows");
                }

                foreach ($options as $oIndex => $option) {
                    if (isset($option['label']) && trim((string) $option['label']) === '') {
                        $errors[] = $this->issue('option_label_empty', "Ada opsi kosong pada pertanyaan '{$question['code']}'.", "{$questionPath}.options[{$oIndex}].label");
                    }
                }

                if (in_array($type, ['number', 'date'], true) && isset($question['min_value'], $question['max_value'])) {
                    if ($question['min_value'] > $question['max_value']) {
                        $errors[] = $this->issue('min_value_greater_than_max', "min_value pertanyaan '{$question['code']}' lebih besar dari max_value.", "{$questionPath}.min_value");
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Adaptasi BranchGraphValidator (cycle DFS + unreachable BFS) yang beroperasi
     * di atas kode section hasil parse JSON, sebelum ada model tersimpan.
     *
     * @return array<int, array{code: string, message: string, path: string}>
     */
    private function validateBranchGraph(array $sections): array
    {
        if (count($sections) <= 1) {
            return [];
        }

        $orderedCodes = array_column($sections, 'code');
        $edges = [];

        foreach ($sections as $index => $section) {
            $code = $section['code'] ?? null;
            if ($code === null) {
                continue;
            }

            $branchTargets = [];
            foreach (array_values($section['questions'] ?? []) as $question) {
                foreach (array_values($question['options'] ?? []) as $option) {
                    $target = $option['target_section_code'] ?? null;
                    if ($target !== null && in_array($target, $orderedCodes, true)) {
                        $branchTargets[] = $target;
                    }
                }
            }

            if ($branchTargets !== []) {
                $edges[$code] = array_values(array_unique($branchTargets));

                continue;
            }

            $next = $orderedCodes[$index + 1] ?? null;
            $edges[$code] = $next !== null ? [$next] : [];
        }

        $errors = [];

        foreach ($orderedCodes as $start) {
            $visited = [];
            $stack = [$start];

            while ($stack !== []) {
                $current = array_pop($stack);

                if ($current === $start && $visited !== []) {
                    $errors[] = $this->issue('branch_cycle_detected', 'Alur branching mengandung siklus tak berujung.', 'sections');

                    break 2;
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

        $first = $orderedCodes[0] ?? null;
        if ($first !== null) {
            $reachable = [$first => true];
            $queue = [$first];

            while ($queue !== []) {
                $current = array_shift($queue);

                foreach ($edges[$current] ?? [] as $next) {
                    if (! isset($reachable[$next])) {
                        $reachable[$next] = true;
                        $queue[] = $next;
                    }
                }
            }

            foreach ($orderedCodes as $code) {
                if (! isset($reachable[$code])) {
                    $errors[] = $this->issue('section_unreachable', "Section '{$code}' tidak bisa dijangkau dari alur manapun.", 'sections');
                }
            }
        }

        return $errors;
    }

    /**
     * Konsistensi skor: dalam satu pertanyaan pilihan, semua opsi sebaiknya
     * sama-sama punya numeric_score atau sama-sama tidak (campuran bikin
     * agregasi Fase 5 tidak konsisten). Dilaporkan sebagai warning, bukan
     * error, karena ini murni sinyal kualitas data — bukan struktur yang
     * merusak fungsi kuesioner.
     *
     * @return array<int, array{code: string, message: string, path: string}>
     */
    private function validateScoringConsistency(array $sections): array
    {
        $warnings = [];

        foreach ($sections as $sIndex => $section) {
            foreach (array_values($section['questions'] ?? []) as $qIndex => $question) {
                $options = array_values($question['options'] ?? []);
                if (count($options) < 2) {
                    continue;
                }

                $withScore = count(array_filter($options, fn ($o) => isset($o['numeric_score'])));

                if ($withScore > 0 && $withScore < count($options)) {
                    $warnings[] = $this->issue(
                        'inconsistent_numeric_score',
                        "Sebagian opsi pertanyaan '{$question['code']}' punya numeric_score, sebagian tidak.",
                        "sections[{$sIndex}].questions[{$qIndex}].options",
                    );
                }
            }
        }

        return $warnings;
    }

    /**
     * @return array{template_code: ?string, sections: int, questions: int, options: int}
     */
    private function buildPreview(?string $templateCode, array $sections): array
    {
        $questionCount = 0;
        $optionCount = 0;

        foreach ($sections as $section) {
            $questions = array_values($section['questions'] ?? []);
            $questionCount += count($questions);

            foreach ($questions as $question) {
                $optionCount += count($question['options'] ?? []);
            }
        }

        return [
            'template_code' => $templateCode,
            'sections' => count($sections),
            'questions' => $questionCount,
            'options' => $optionCount,
        ];
    }

    /**
     * @return array{code: string, message: string, path: string}
     */
    private function issue(string $code, string $message, string $path): array
    {
        return ['code' => $code, 'message' => $message, 'path' => $path];
    }

    /**
     * @return array{template_code: ?string, sections: int, questions: int, options: int}
     */
    private function emptyPreview(): array
    {
        return ['template_code' => null, 'sections' => 0, 'questions' => 0, 'options' => 0];
    }
}
