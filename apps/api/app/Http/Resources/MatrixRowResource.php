<?php

namespace App\Http\Resources;

use App\Domains\Questionnaires\Models\MatrixRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MatrixRow
 */
class MatrixRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'sort_order' => $this->sort_order,
        ];
    }
}
