<?php

namespace App\Http\Resources;

use App\Domains\Campaigns\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Campaign
 */
class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'questionnaire_id' => $this->questionnaire_id,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'target_rule_json' => $this->target_rule_json,
            'denominator_count' => $this->denominator_count,
            'minimum_segment_size' => $this->minimum_segment_size,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'paused_at' => $this->paused_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
