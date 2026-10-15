<?php

namespace App\Domains\Questionnaires\Actions;

use App\Domains\Questionnaires\Models\SurveyTemplate;
use App\Domains\Questionnaires\Models\SurveyTemplateOption;
use App\Domains\Questionnaires\Models\SurveyTemplateQuestion;
use App\Domains\Questionnaires\Models\SurveyTemplateSection;

// Kebalikan struktural dari CommitTemplateImportAction: membangun ulang JSON
// skema import (FSD §15.3) dari data ternormalisasi di database, termasuk
// membalik validation_json/analytics_config_json ke key rata dan
// target_section_id ke target_section_code. Key opsional yang bernilai null
// selalu di-omit (bukan null literal) supaya hasilnya pasti valid saat
// diimpor ulang lewat TemplateImportValidator tanpa perlu toleransi khusus.
class ExportTemplateAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(SurveyTemplate $template): array
    {
        $template->loadMissing([
            'sections.questions.options.targetSection',
            'sections.questions.matrixRows',
        ]);

        return $this->filterNulls([
            'schema_version' => '1.0',
            'template' => $this->filterNulls([
                'code' => $template->code,
                'title' => $template->title,
                'description' => $template->description,
                'category' => $template->category,
                'audience_type' => $template->audience_type,
                'identity_mode' => $template->identity_mode,
                'language' => $template->language,
            ]),
            'sections' => $template->sections->map(fn (SurveyTemplateSection $section) => $this->exportSection($section))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function exportSection(SurveyTemplateSection $section): array
    {
        return $this->filterNulls([
            'code' => $section->code,
            'title' => $section->title,
            'description' => $section->description,
            'order' => $section->sort_order,
            'questions' => $section->questions->map(fn (SurveyTemplateQuestion $question) => $this->exportQuestion($question))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function exportQuestion(SurveyTemplateQuestion $question): array
    {
        return $this->filterNulls([
            'code' => $question->code,
            'text' => $question->text,
            'type' => $question->type,
            'help_text' => $question->help_text,
            'required' => $question->required,
            'allow_not_applicable' => $question->allow_not_applicable,
            'min_value' => $question->validation_json['min_value'] ?? null,
            'max_value' => $question->validation_json['max_value'] ?? null,
            'order' => $question->sort_order,
            'chart_type' => $question->analytics_config_json['chart_type'] ?? null,
            'options' => $question->options->map(fn (SurveyTemplateOption $option) => $this->exportOption($option))->all(),
            'matrix_rows' => $question->matrixRows->map(fn ($row) => $this->filterNulls([
                'code' => $row->code,
                'label' => $row->label,
                'order' => $row->sort_order,
            ]))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function exportOption(SurveyTemplateOption $option): array
    {
        return $this->filterNulls([
            'code' => $option->code,
            'label' => $option->label,
            'numeric_score' => $option->numeric_score,
            'is_positive' => $option->is_positive,
            'is_not_applicable' => $option->is_not_applicable,
            'order' => $option->sort_order,
            'target_section_code' => $option->targetSection?->code,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterNulls(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null);
    }
}
