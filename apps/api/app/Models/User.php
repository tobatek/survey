<?php

namespace App\Models;

use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\UserScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// Tidak ada password/remember_token — satu-satunya jalur masuk adalah SSO
// Portal (docs/15_SSO_PORTAL_VALIDASI_SEPARATION_PLAN.md). Role/scope
// ditentukan lokal, independen dari role di Portal (FSD §5.2-5.3).
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'tenant_id',
        'hub_user_id',
        'email',
        'display_name',
        'status',
        'last_login_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(UserScope::class);
    }

    public function hasRole(string ...$codes): bool
    {
        return $this->roles->pluck('code')->intersect($codes)->isNotEmpty();
    }
}
