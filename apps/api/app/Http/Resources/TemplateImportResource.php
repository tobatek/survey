<?php

namespace App\Http\Resources;

use App\Domains\Questionnaires\Models\TemplateImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TemplateImport
 */
class TemplateImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'status' => $this->status,
            'errors' => $this->errors_json ?? [],
            'warnings' => $this->warnings_json ?? [],
            'preview' => $this->preview_json,
            'checksum' => $this->checksum,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'committed_at' => $this->committed_at?->toIso8601String(),
            'committed_template_id' => $this->committed_template_id,
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader?->display_name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
