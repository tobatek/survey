<?php

namespace App\Domains\Respondents\Support;

use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Employees\Models\Employee;
use App\Domains\Students\Models\Student;
use App\Models\User;

// Tidak ada kolom penghubung langsung antara users dan students/employees
// (SSO Portal & sync master data terpisah) -- satu-satunya cara mencocokkan
// yang tersedia sekarang adalah kesamaan alamat email institusi. Cukup untuk
// MVP karena keduanya bersumber dari identitas SSO yang sama.
class ResolveAudienceSubject
{
    /**
     * @return array{type: string, id: string}|null
     */
    public function forUser(User $user): ?array
    {
        $student = Student::query()->where('institutional_email', $user->email)->first();

        if ($student !== null) {
            return ['type' => AudienceMember::SUBJECT_STUDENT, 'id' => $student->id];
        }

        $employee = Employee::query()->where('institutional_email', $user->email)->first();

        if ($employee !== null) {
            return ['type' => AudienceMember::SUBJECT_EMPLOYEE, 'id' => $employee->id];
        }

        return null;
    }
}
