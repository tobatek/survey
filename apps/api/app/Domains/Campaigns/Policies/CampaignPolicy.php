<?php

namespace App\Domains\Campaigns\Policies;

use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Identity\Models\Role;
use App\Models\User;

// FSD §5.3 tabel otorisasi + pemeriksaan tenant resource-level (lihat
// QuestionnairePolicy untuk pola yang sama) — middleware `role:...` cuma
// gerbang role kasar, di sini ditambahkan pemeriksaan tenant supaya satu
// tenant tidak bisa mengakses Campaign milik tenant lain lewat tebakan UUID.
class CampaignPolicy
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

    public function view(User $user, Campaign $campaign): bool
    {
        return $this->viewAny($user) && $campaign->tenant_id === $user->tenant_id;
    }

    // FSD §5.3/§12.2 -- "raw export/komentar" adalah tier otorisasi TERPISAH
    // dari "lihat hasil agregat" (viewAny/view di atas). LPM Admin dkk TIDAK
    // otomatis lolos di sini kecuali juga diberi role raw_data_analyst --
    // sengaja tidak memanggil viewAny(), syarat rolenya beda total.
    public function viewComments(User $user, Campaign $campaign): bool
    {
        return $user->loadMissing('roles')->hasRole(Role::RAW_DATA_ANALYST)
            && $campaign->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->loadMissing('roles')->hasRole(Role::LPM_ADMIN, Role::SURVEY_OPERATOR);
    }

    public function manage(User $user, Campaign $campaign): bool
    {
        return $this->create($user) && $campaign->tenant_id === $user->tenant_id;
    }
}
