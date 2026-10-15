<?php

namespace App\Domains\Campaigns\Policies;

use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Respondents\Support\ResolveAudienceSubject;
use App\Models\User;

// Beda dari policy lain di domain ini (role-based: admin/operator/viewer) --
// eligibility dimiliki RESPONDEN biasa, jadi otorisasinya berbasis kecocokan
// data (subject_type/subject_id), bukan role.
class EligibilityPolicy
{
    public function start(User $user, Eligibility $eligibility): bool
    {
        $subject = app(ResolveAudienceSubject::class)->forUser($user);

        if ($subject === null) {
            return false;
        }

        $eligibility->loadMissing('audienceMember');

        return $eligibility->audienceMember->subject_type === $subject['type']
            && $eligibility->audienceMember->subject_id === $subject['id'];
    }
}
