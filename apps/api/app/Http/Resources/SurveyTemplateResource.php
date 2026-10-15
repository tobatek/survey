<?php

namespace App\Http\Resources;

use App\Domains\Questionnaires\Models\SurveyTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SurveyTemplate
 */
class SurveyTemplateResource extends JsonResource
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
            'language' => $this->language,
            'status' => $this->status,
            'checksum' => $this->checksum,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
