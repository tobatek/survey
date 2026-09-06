<?php

namespace App\Domains\Integrations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// FSD §16.2 sync_runs. RULE-D02: sync gagal tidak menonaktifkan seluruh
// master — status kegagalan tercatat di sini per-run, students/employees
// tidak pernah dihapus massal hanya karena satu run gagal.
class SyncRun extends Model
{
    use HasUuids;

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'mode',
        'status',
        'triggered_by',
        'processed_count',
        'upserted_count',
        'error_count',
        'correlation_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(SyncError::class);
    }
}
