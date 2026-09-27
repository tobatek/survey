<?php

namespace App\Domains\Respondents\Actions;

use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Respondents\Exceptions\SurveyAccessException;
use App\Domains\Respondents\Models\ResponseSession;
use Illuminate\Support\Facades\DB;

// FSD §11.4 (langkah 1-3, sebelum submit) & §12.1 -- membuat atau
// melanjutkan sesi pengisian. Dipakai dua jalur: identified (dari halaman
// Survei Saya, $subject dari user login) dan anonymous (dari link token
// undangan, $rawToken dipakai turunkan anonymous_nonce_hash supaya klik
// ulang link yang sama meneruskan draft yang sama, TANPA pernah menyimpan
// eligibility_id di sesi anonymous -- lihat komentar migrasi
// response_sessions).
class StartResponseSessionAction
{
    /**
     * @param  array{type: string, id: string}|null  $subject  hanya diisi mode identified
     */
    public function execute(Eligibility $eligibility, string $mode, ?array $subject, ?string $rawToken = null): ResponseSession
    {
        $eligibility->loadMissing(['campaign', 'audienceMember']);
        $campaign = $eligibility->campaign;

        $this->assertCampaignOpen($campaign);
        $this->assertEligibilityUsable($eligibility);

        return DB::transaction(function () use ($eligibility, $campaign, $mode, $subject, $rawToken) {
            $session = $mode === ResponseSession::MODE_IDENTIFIED
                ? ResponseSession::query()->where('eligibility_id', $eligibility->id)->first()
                : ResponseSession::query()->where('anonymous_nonce_hash', $this->nonceHash($rawToken))->first();

            if ($session === null) {
                $session = ResponseSession::create([
                    'campaign_id' => $campaign->id,
                    'snapshot_id' => $campaign->snapshot_id,
                    'eligibility_id' => $mode === ResponseSession::MODE_IDENTIFIED ? $eligibility->id : null,
                    'mode' => $mode,
                    'subject_type' => $mode === ResponseSession::MODE_IDENTIFIED ? $subject['type'] : null,
                    'subject_id' => $mode === ResponseSession::MODE_IDENTIFIED ? $subject['id'] : null,
                    'anonymous_nonce_hash' => $mode === ResponseSession::MODE_ANONYMOUS ? $this->nonceHash($rawToken) : null,
                    'segment_snapshot_json' => $eligibility->audienceMember->attributes_snapshot_json,
                    'status' => ResponseSession::STATUS_DRAFT,
                    'started_at' => now(),
                    'expires_at' => now()->addDays(ResponseSession::DRAFT_TTL_DAYS),
                    'tenant_id' => $campaign->tenant_id,
                ]);
            }

            if (in_array($eligibility->status, [Eligibility::STATUS_ELIGIBLE, Eligibility::STATUS_INVITED, Eligibility::STATUS_OPENED], true)) {
                $eligibility->update(['status' => Eligibility::STATUS_STARTED, 'started_at' => $eligibility->started_at ?? now()]);
            }

            return $session;
        });
    }

    private function nonceHash(?string $rawToken): string
    {
        return hash('sha256', $rawToken.'|response_session');
    }

    private function assertCampaignOpen(Campaign $campaign): void
    {
        match ($campaign->status) {
            Campaign::STATUS_ACTIVE => null,
            Campaign::STATUS_PAUSED => throw new SurveyAccessException('SURVEY_PAUSED', 'Survei ini sedang dijeda oleh pengelola.'),
            Campaign::STATUS_CLOSED => throw new SurveyAccessException('SURVEY_CLOSED', 'Survei ini sudah ditutup.'),
            default => throw new SurveyAccessException('SURVEY_NOT_STARTED', 'Survei ini belum dimulai.'),
        };
    }

    private function assertEligibilityUsable(Eligibility $eligibility): void
    {
        match ($eligibility->status) {
            Eligibility::STATUS_COMPLETED => throw new SurveyAccessException('ALREADY_COMPLETED', 'Anda sudah menyelesaikan survei ini.'),
            Eligibility::STATUS_EXCLUDED => throw new SurveyAccessException('NOT_ELIGIBLE', 'Anda tidak termasuk target survei ini.'),
            Eligibility::STATUS_EXPIRED => throw new SurveyAccessException('SURVEY_CLOSED', 'Batas waktu pengisian survei ini sudah lewat.'),
            default => null,
        };
    }
}
