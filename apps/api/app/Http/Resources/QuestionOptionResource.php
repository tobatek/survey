<?php

namespace App\Http\Resources;

use App\Domains\Questionnaires\Models\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuestionOption
 */
class QuestionOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'numeric_score' => $this->numeric_score,
            'is_positive' => $this->is_positive,
            'is_not_applicable' => $this->is_not_applicable,
            'target_section_id' => $this->target_section_id,
            'sort_order' => $this->sort_order,
        ];
    }
}
