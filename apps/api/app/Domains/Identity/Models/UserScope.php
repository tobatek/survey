<?php

namespace App\Domains\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// FSD §16.1 — pembatasan tambahan di luar role, mis. operator yang hanya
// boleh melihat prodi/unit tertentu. Belum ada UI pengelolaan di Fase 1;
// Policy bisa mulai memeriksa tabel ini begitu kebutuhan scoping muncul.
class UserScope extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'scope_type', 'scope_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
