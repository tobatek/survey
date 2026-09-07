<?php

namespace App\Http\Resources;

use App\Domains\Questionnaires\Models\QuestionnaireSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuestionnaireSection
 */
class QuestionnaireSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
