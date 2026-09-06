<?php

namespace App\Domains\Integrations\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncError extends Model
{
    use HasUuids;

    protected $fillable = ['sync_run_id', 'source_id', 'error_code', 'message', 'rejected_payload'];

    protected function casts(): array
    {
        return ['rejected_payload' => 'array'];
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }
}
