<?php

namespace App\Domains\Questionnaires\Actions;

use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\SurveyTemplate;
use App\Models\User;

// Melengkapi exit criteria Fase 2 "admin dapat membuat kuesioner dari nol/
// template" — sebelumnya Questionnaire.template_id cuma tersimpan sebagai FK
// tanpa pernah menyalin struktur. Pola penyalinan meniru persis
// QuestionnaireController::duplicate() (sumbernya Questionnaire lain), di
// sini sumbernya SurveyTemplate + child model dari Import Template (slice 2).
class CreateQuestionnaireFromTemplateAction
{
    public function __construct(private readonly CreateQuestionnaireAction $createQuestionnaireAction) {}

    /**
     * @param  array{code: string, title: string, description?: string|null, category?: string|null, audience_type?: string|null, identity_mode?: string|null}  $data
     */
    public function execute(SurveyTemplate $template, array $data, User $creator): Questionnaire
    {
        $template->loadMissing(['sections.questions.options.targetSection', 'sections.questions.matrixRows']);

        $questionnaire = $this->createQuestionnaireAction->execute([
            'code' => $data['code'],
            'title' => $data['title'],
            'description' => $data['description'] ?? $template->description,
            'category' => $data['category'] ?? $template->category,
            'audience_type' => $data['audience_type'] ?? $template->audience_type,
            'identity_mode' => $data['identity_mode'] ?? $template->identity_mode,
            'template_id' => $template->id,
        ], $creator);

        $sectionIdByTemplateSectionCode = [];

        foreach ($template->sections as $section) {
            $newSection = $questionnaire->sections()->create([
                'code' => $section->code,
                'title' => $section->title,
                'description' => $section->description,
                'sort_order' => $section->sort_order,
            ]);

            $sectionIdByTemplateSectionCode[$section->code] = $newSection->id;
        }

        foreach ($template->sections as $section) {
            $newSectionId = $sectionIdByTemplateSectionCode[$section->code];

            foreach ($section->questions as $question) {
                $newQuestion = $questionnaire->questions()->create([
                    'section_id' => $newSectionId,
                    'code' => $question->code,
                    'type' => $question->type,
                    'text' => $question->text,
                    'help_text' => $question->help_text,
                    'required' => $question->required,
                    'allow_not_applicable' => $question->allow_not_applicable,
                    'validation_json' => $question->validation_json,
                    'analytics_config_json' => $question->analytics_config_json,
                    'sort_order' => $question->sort_order,
                ]);

                foreach ($question->options as $option) {
                    $targetSection = $option->targetSection;

                    $newQuestion->options()->create([
                        'code' => $option->code,
                        'label' => $option->label,
                        'numeric_score' => $option->numeric_score,
                        'is_positive' => $option->is_positive,
                        'is_not_applicable' => $option->is_not_applicable,
                        'target_section_id' => $targetSection !== null ? ($sectionIdByTemplateSectionCode[$targetSection->code] ?? null) : null,
                        'sort_order' => $option->sort_order,
                    ]);
                }

                foreach ($question->matrixRows as $row) {
                    $newQuestion->matrixRows()->create([
                        'code' => $row->code,
                        'label' => $row->label,
                        'sort_order' => $row->sort_order,
                    ]);
                }
            }
        }

        return $questionnaire->fresh(['sections.questions.options', 'sections.questions.matrixRows']);
    }
}
