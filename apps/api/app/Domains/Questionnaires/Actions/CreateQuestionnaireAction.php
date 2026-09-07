<?php

namespace App\Domains\Questionnaires\Actions;

use App\Domains\Questionnaires\Models\Questionnaire;
use App\Models\User;

class CreateQuestionnaireAction
{
    /**
     * @param  array{code: string, title: string, description?: string|null, category?: string|null, audience_type: string, identity_mode: string, template_id?: string|null}  $data
     */
    public function execute(array $data, User $creator): Questionnaire
    {
        $questionnaire = Questionnaire::query()->create([
            'tenant_id' => $creator->tenant_id,
            'code' => $data['code'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'audience_type' => $data['audience_type'],
            'identity_mode' => $data['identity_mode'],
            'template_id' => $data['template_id'] ?? null,
            'status' => Questionnaire::STATUS_DRAFT,
            'allow_draft_save' => true,
            'structure_version' => 1,
            'lock_version' => 1,
            'created_by' => $creator->id,
        ]);

        // Kolom dengan DB default (mis. via migration) tidak otomatis terisi
        // di instance in-memory setelah create() — fresh() memastikan response
        // pertama sudah konsisten dengan yang tersimpan.
        return $questionnaire->fresh();
    }
}
