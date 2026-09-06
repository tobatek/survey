<?php

namespace App\Domains\Employees\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'source_system',
        'source_id',
        'employee_number',
        'name',
        'normalized_name',
        'institutional_email',
        'employee_type',
        'work_unit_name',
        'position_name',
        'status',
        'source_updated_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'source_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
