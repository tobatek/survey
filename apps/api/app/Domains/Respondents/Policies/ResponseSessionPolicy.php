<?php

namespace App\Domains\Respondents\Policies;

use App\Domains\Respondents\Models\ResponseSession;
use App\Domains\Respondents\Support\ResolveAudienceSubject;
use App\Models\User;

// Sama seperti App\Domains\Campaigns\Policies\EligibilityPolicy -- sesi
// respondent dimiliki RESPONDEN biasa, jadi otorisasinya berbasis kecocokan
// subject, bukan role. Hanya dipakai untuk mode identified (mode anonymous
// tidak punya user login sama sekali -- lihat
// Http\Controllers\Api\Concerns\HandlesDraftLock).
class ResponseSessionPolicy
{
    public function access(User $user, ResponseSession $session): bool
    {
        if ($session->mode !== ResponseSession::MODE_IDENTIFIED) {
            return false;
        }

        $subject = app(ResolveAudienceSubject::class)->forUser($user);

        if ($subject === null) {
            return false;
        }

        return $session->subject_type === $subject['type']
            && $session->subject_id === $subject['id'];
    }
}
