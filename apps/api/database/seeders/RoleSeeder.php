<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\Role;
use Illuminate\Database\Seeder;

// Role baseline FSD §5.2. Wajib ada sebelum login SSO pertama kali, karena
// FindOrCreateUserFromSsoIdentityAction memberi role default
// student_respondent/employee_respondent ke user baru.
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [Role::LPM_ADMIN, 'Admin Institusi/LPM'],
            [Role::SURVEY_OPERATOR, 'Operator Survei'],
            [Role::EXECUTIVE_VIEWER, 'Pimpinan/Viewer'],
            [Role::TECHNICAL_ADMIN, 'Administrator Teknis'],
            [Role::STUDENT_RESPONDENT, 'Mahasiswa'],
            [Role::EMPLOYEE_RESPONDENT, 'Pegawai'],
            [Role::RAW_DATA_ANALYST, 'Analis Data Mentah'],
        ];

        foreach ($roles as [$code, $name]) {
            Role::query()->updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
