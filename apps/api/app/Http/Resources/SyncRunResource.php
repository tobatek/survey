<?php

namespace App\Http\Resources;

use App\Domains\Integrations\Models\SyncRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SyncRun
 */
class SyncRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'mode' => $this->mode,
            'status' => $this->status,
            'processed_count' => $this->processed_count,
            'upserted_count' => $this->upserted_count,
            'error_count' => $this->error_count,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
