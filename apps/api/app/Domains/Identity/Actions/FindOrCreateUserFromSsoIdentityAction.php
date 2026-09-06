<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// JIT create/update — FSD §5.1 "JIT create/update akun hanya untuk atribut
// minimum". Role default ditentukan dari `type` yang dikirim Portal
// (student|staff); role admin (lpm_admin dkk.) HARUS ditetapkan manual
// lewat pengelolaan RBAC internal, tidak pernah otomatis dari SSO.
class FindOrCreateUserFromSsoIdentityAction
{
    /**
     * @param  array{id: string, email: string, name: string, type: string, nim?: string, nip?: string, roles: string[]}  $identity
     */
    public function execute(array $identity): User
    {
        return DB::transaction(function () use ($identity) {
            /** @var User $user */
            $user = User::query()->updateOrCreate(
                ['tenant_id' => 'default', 'hub_user_id' => $identity['id']],
                [
                    'email' => $identity['email'],
                    'display_name' => $identity['name'],
                    'status' => 'active',
                    'last_login_at' => now(),
                ],
            );

            if ($user->roles()->count() === 0) {
                $defaultRoleCode = $identity['type'] === 'student'
                    ? Role::STUDENT_RESPONDENT
                    : Role::EMPLOYEE_RESPONDENT;

                $role = Role::query()->where('code', $defaultRoleCode)->first();

                if ($role !== null) {
                    $user->roles()->attach($role);
                }
            }

            return $user->fresh('roles');
        });
    }
}
