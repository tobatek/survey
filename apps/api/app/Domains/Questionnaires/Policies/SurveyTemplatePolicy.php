<?php

namespace App\Domains\Questionnaires\Policies;

use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\SurveyTemplate;
use App\Models\User;

// Dipakai SurveyTemplateController. Scoping tenant per-baris di listing
// sudah benar lewat query manual di controller — viewAny di sini cukup
// gerbang role untuk melihat daftar template sama sekali. update dipakai
// endpoint aktifkan/nonaktifkan (FSD §17.7).
class SurveyTemplatePolicy
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

    public function update(User $user, SurveyTemplate $template): bool
    {
        return $user->loadMissing('roles')->hasRole(Role::LPM_ADMIN, Role::SURVEY_OPERATOR)
            && $template->tenant_id === $user->tenant_id;
    }
}
