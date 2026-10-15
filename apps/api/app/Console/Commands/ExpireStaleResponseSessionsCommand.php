<?php

namespace App\Console\Commands;

use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Respondents\Models\ResponseSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Fase 4 slice 6 -- sesi draft yang expires_at-nya sudah lewat (TTL rolling,
// lihat ResponseSession::DRAFT_TTL_DAYS) ditandai expired. Pola sama persis
// CloseExpiredCampaignsCommand: conditional update per baris sebagai jaring
// pengaman race. Untuk mode identified, eligibility yang belum terminal ikut
// ditutup; mode anonymous TIDAK disentuh (sengaja tidak menyimpan
// eligibility_id -- lihat TransitionCampaignStatusAction untuk jalur
// penutupan eligibility anonymous lewat penutupan campaign).
class ExpireStaleResponseSessionsCommand extends Command
{
    protected $signature = 'responses:expire-stale-sessions';

    protected $description = 'Tandai sesi pengisian survei (draft) yang sudah lewat expires_at sebagai expired';

    public function handle(): int
    {
        $sessions = ResponseSession::query()
            ->where('status', ResponseSession::STATUS_DRAFT)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $expired = 0;

        foreach ($sessions as $session) {
            DB::transaction(function () use ($session, &$expired) {
                $affected = ResponseSession::query()
                    ->where('id', $session->id)
                    ->where('status', ResponseSession::STATUS_DRAFT)
                    ->update(['status' => ResponseSession::STATUS_EXPIRED]);

                if ($affected === 0) {
                    return;
                }

                $expired++;

                if ($session->mode === ResponseSession::MODE_IDENTIFIED && $session->eligibility_id !== null) {
                    Eligibility::query()
                        ->where('id', $session->eligibility_id)
                        ->whereNotIn('status', [Eligibility::STATUS_COMPLETED, Eligibility::STATUS_EXCLUDED, Eligibility::STATUS_EXPIRED])
                        ->update(['status' => Eligibility::STATUS_EXPIRED, 'expired_at' => now()]);
                }
            });
        }

        $this->info("Menandai {$expired} dari {$sessions->count()} sesi draft sebagai expired.");

        return self::SUCCESS;
    }
}
