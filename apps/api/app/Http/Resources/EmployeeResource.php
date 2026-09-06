<?php

namespace App\Http\Resources;

use App\Domains\Employees\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'name' => $this->name,
            'email' => $this->institutional_email,
            'employee_type' => $this->employee_type,
            'work_unit' => $this->work_unit_name,
            'position' => $this->position_name,
            'status' => $this->status,
            'source' => $this->source_system,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
