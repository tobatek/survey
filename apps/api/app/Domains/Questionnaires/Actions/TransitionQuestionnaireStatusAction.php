<?php

namespace App\Domains\Questionnaires\Actions;

use App\Domains\Audit\Actions\RecordAuditEventAction;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Support\QuestionnaireLockGuard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// FSD §7.6 — state machine: draft->ready (lihat FinalizeQuestionnaireAction),
// ready->active<->paused->closed, dan ready->draft (hanya jika belum pernah
// active). Tidak ada closed->active (harus duplicate/campaign baru).
class TransitionQuestionnaireStatusAction
{
    /** @var array<string, array<int, string>> */
    private const ALLOWED = [
        Questionnaire::STATUS_READY => [Questionnaire::STATUS_ACTIVE, Questionnaire::STATUS_DRAFT],
        Questionnaire::STATUS_ACTIVE => [Questionnaire::STATUS_PAUSED, Questionnaire::STATUS_CLOSED],
        Questionnaire::STATUS_PAUSED => [Questionnaire::STATUS_ACTIVE, Questionnaire::STATUS_CLOSED],
    ];

    public function __construct(
        private readonly QuestionnaireLockGuard $lockGuard,
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function execute(Questionnaire $questionnaire, string $targetStatus, ?int $ifMatch, User $actor): Questionnaire
    {
        return DB::transaction(function () use ($questionnaire, $targetStatus, $ifMatch, $actor) {
            $this->lockGuard->assertMatches($questionnaire, $ifMatch);

            $allowed = self::ALLOWED[$questionnaire->status] ?? [];

            if (! in_array($targetStatus, $allowed, true)) {
                throw new RuntimeException("Transisi status dari '{$questionnaire->status}' ke '{$targetStatus}' tidak diizinkan.");
            }

            // Catatan: `ready->draft` hanya tercapai selama status masih 'ready'
            // (lihat peta ALLOWED) — begitu questionnaire pernah ke 'active',
            // statusnya tidak lagi 'ready', sehingga graf ini sendiri sudah
            // mencegah "kembali ke draft setelah pernah aktif" tanpa perlu
            // flag tambahan.
            $questionnaire->status = $targetStatus;
            $questionnaire->save();
            $this->lockGuard->bump($questionnaire);

            $this->recordAuditEvent->execute(
                action: 'questionnaire.transition.'.$targetStatus,
                result: 'success',
                actor: $actor,
                resourceType: 'questionnaire',
                resourceId: $questionnaire->id,
            );

            return $questionnaire->fresh();
        });
    }
}
