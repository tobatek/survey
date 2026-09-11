<?php

namespace App\Domains\Questionnaires\Policies;

use App\Domains\Identity\Models\Role;
use App\Models\User;

// Dipakai SurveyTemplateController@index. Scoping tenant per-baris di
// listing sudah benar lewat query manual di controller — di sini cukup
// gerbang role untuk melihat daftar template sama sekali.
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
}
