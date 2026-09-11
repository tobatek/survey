<?php

namespace App\Domains\Questionnaires\Policies;

use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\TemplateImport;
use App\Models\User;

// Sama alasan dengan QuestionnairePolicy — staging import (FSD §15.4)
// diakses lewat route-model-binding by ID, jadi perlu pemeriksaan tenant di
// sini supaya tenant lain tidak bisa melihat/mengonfirmasi staging import
// tenant lain lewat tebakan UUID.
class TemplateImportPolicy
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

    public function view(User $user, TemplateImport $templateImport): bool
    {
        return $this->viewAny($user) && $templateImport->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->loadMissing('roles')->hasRole(Role::LPM_ADMIN, Role::SURVEY_OPERATOR);
    }

    public function commit(User $user, TemplateImport $templateImport): bool
    {
        return $this->create($user) && $templateImport->tenant_id === $user->tenant_id;
    }
}
