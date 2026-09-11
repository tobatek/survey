<?php

namespace App\Domains\Questionnaires\Actions;

use App\Domains\Audit\Actions\RecordAuditEventAction;
use App\Domains\Questionnaires\Exceptions\TemplateImportNotCommittableException;
use App\Domains\Questionnaires\Models\SurveyTemplate;
use App\Domains\Questionnaires\Models\TemplateImport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// FSD §15.4 — commit transaksional all-or-nothing: satu error membatalkan
// transaction. Mengikuti pola FinalizeQuestionnaireAction (validasi -> guard
// -> transaction -> audit event).
class CommitTemplateImportAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function execute(TemplateImport $import, User $actor): SurveyTemplate
    {
        if ($import->committed_at !== null) {
            throw new TemplateImportNotCommittableException('already_committed', 'Import ini sudah pernah dikonfirmasi sebelumnya.');
        }

        if ($import->isExpired()) {
            throw new TemplateImportNotCommittableException('expired', 'Sesi staging import ini sudah kedaluwarsa, silakan unggah ulang.');
        }

        if ($import->status !== TemplateImport::STATUS_VALIDATED) {
            throw new TemplateImportNotCommittableException(
                'has_errors',
                'Import ini masih punya error validasi dan tidak bisa dikonfirmasi.',
                $import->errors_json ?? [],
            );
        }

        return DB::transaction(function () use ($import, $actor) {
            $payload = $import->raw_payload;
            $templateData = $payload['template'];

            $template = SurveyTemplate::query()->create([
                'tenant_id' => $import->tenant_id,
                'code' => $templateData['code'],
                'title' => $templateData['title'],
                'description' => $templateData['description'] ?? null,
                'category' => $templateData['category'],
                'audience_type' => $templateData['audience_type'],
                'identity_mode' => $templateData['identity_mode'],
                'language' => $templateData['language'],
                'schema_version' => 1,
                'status' => 'active',
                'source_import_id' => $import->id,
                'checksum' => $import->checksum,
                'created_by' => $actor->id,
            ]);

            $sectionIdByCode = [];

            foreach (array_values($payload['sections'] ?? []) as $sIndex => $sectionData) {
                $section = $template->sections()->create([
                    'code' => $sectionData['code'],
                    'title' => $sectionData['title'],
                    'description' => $sectionData['description'] ?? null,
                    'sort_order' => $sectionData['order'] ?? $sIndex,
                ]);

                $sectionIdByCode[$sectionData['code']] = $section->id;
            }

            foreach (array_values($payload['sections'] ?? []) as $sIndex => $sectionData) {
                $sectionId = $sectionIdByCode[$sectionData['code']];

                foreach (array_values($sectionData['questions'] ?? []) as $qIndex => $questionData) {
                    $question = $template->questions()->create([
                        'section_id' => $sectionId,
                        'code' => $questionData['code'],
                        'type' => $questionData['type'],
                        'text' => $questionData['text'],
                        'help_text' => $questionData['help_text'] ?? null,
                        'required' => $questionData['required'] ?? false,
                        'allow_not_applicable' => $questionData['allow_not_applicable'] ?? false,
                        'validation_json' => $this->buildValidationJson($questionData),
                        'analytics_config_json' => isset($questionData['chart_type']) ? ['chart_type' => $questionData['chart_type']] : null,
                        'sort_order' => $questionData['order'] ?? $qIndex,
                    ]);

                    foreach (array_values($questionData['options'] ?? []) as $oIndex => $optionData) {
                        $targetCode = $optionData['target_section_code'] ?? null;

                        $question->options()->create([
                            'code' => $optionData['code'],
                            'label' => $optionData['label'],
                            'numeric_score' => $optionData['numeric_score'] ?? null,
                            'is_positive' => $optionData['is_positive'] ?? true,
                            'is_not_applicable' => $optionData['is_not_applicable'] ?? false,
                            'target_section_id' => $targetCode !== null ? ($sectionIdByCode[$targetCode] ?? null) : null,
                            'sort_order' => $optionData['order'] ?? $oIndex,
                        ]);
                    }

                    foreach (array_values($questionData['matrix_rows'] ?? []) as $rIndex => $rowData) {
                        $question->matrixRows()->create([
                            'code' => $rowData['code'],
                            'label' => $rowData['label'],
                            'sort_order' => $rowData['order'] ?? $rIndex,
                        ]);
                    }
                }
            }

            $import->update([
                'status' => TemplateImport::STATUS_COMMITTED,
                'committed_at' => now(),
                'committed_template_id' => $template->id,
            ]);

            $this->recordAuditEvent->execute(
                action: 'survey_template.import',
                result: 'success',
                actor: $actor,
                resourceType: 'survey_template',
                resourceId: $template->id,
                context: [
                    'checksum' => $template->checksum,
                    'source_import_id' => $import->id,
                    'template_code' => $template->code,
                ],
            );

            return $template->fresh();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildValidationJson(array $questionData): ?array
    {
        $validation = [];

        if (isset($questionData['min_value'])) {
            $validation['min_value'] = $questionData['min_value'];
        }

        if (isset($questionData['max_value'])) {
            $validation['max_value'] = $questionData['max_value'];
        }

        return $validation === [] ? null : $validation;
    }
}
