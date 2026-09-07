<?php

namespace App\Http\Resources;

use App\Domains\Questionnaires\Models\Questionnaire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Questionnaire
 */
class QuestionnaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'audience_type' => $this->audience_type,
            'identity_mode' => $this->identity_mode,
            'status' => $this->status,
            'allow_draft_save' => $this->allow_draft_save,
            'privacy_notice' => $this->privacy_notice,
            'confirmation_text' => $this->confirmation_text,
            'structure_version' => $this->structure_version,
            'lock_version' => $this->lock_version,
            'snapshot_checksum' => $this->snapshot_checksum,
            'is_editable' => $this->isEditable(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'sections' => QuestionnaireSectionResource::collection($this->whenLoaded('sections')),
        ];
    }
}
