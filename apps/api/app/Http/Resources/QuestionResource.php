<?php

namespace App\Http\Resources;

use App\Domains\Questionnaires\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Question
 */
class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'code' => $this->code,
            'type' => $this->type,
            'text' => $this->text,
            'help_text' => $this->help_text,
            'required' => $this->required,
            'allow_not_applicable' => $this->allow_not_applicable,
            'validation' => $this->validation_json,
            'sort_order' => $this->sort_order,
            'options' => QuestionOptionResource::collection($this->whenLoaded('options')),
            'matrix_rows' => MatrixRowResource::collection($this->whenLoaded('matrixRows')),
        ];
    }
}
