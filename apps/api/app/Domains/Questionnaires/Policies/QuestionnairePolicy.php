<?php

namespace App\Domains\Questionnaires\Policies;

use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Models\User;

// FSD §5.3 tabel otorisasi + pemeriksaan resource-level yang dijanjikan
// komentar EnsureUserHasRole (lihat app/Http/Middleware/EnsureUserHasRole.php):
// middleware `role:...` di routes/api.php cuma gerbang role kasar, di sini
// ditambahkan pemeriksaan tenant supaya satu tenant tidak bisa mengakses
// Questionnaire milik tenant lain lewat tebakan UUID.
class QuestionnairePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->loadMissing('roles')->hasRole(
            Role::LPM_ADMIN,
            Role::SURVEY_OPERATOR,
            Role::EXECUTIVE_VIEWER,
            Role::TECHNICAL_ADMIN,
        );
    }

    public function view(User $user, Questionnaire $questionnaire): bool
    {
        return $this->viewAny($user) && $questionnaire->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->loadMissing('roles')->hasRole(Role::LPM_ADMIN, Role::SURVEY_OPERATOR);
    }

    public function update(User $user, Questionnaire $questionnaire): bool
    {
        return $this->create($user) && $questionnaire->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Questionnaire $questionnaire): bool
    {
        return $this->update($user, $questionnaire);
    }
}
