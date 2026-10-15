<?php

namespace App\Domains\Audit\Actions;

use App\Domains\Audit\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Str;

// FSD §22 — audit event wajib untuk login, sync, aksi sensitif lain.
// Payload di $context adalah tanggung jawab pemanggil untuk disanitasi:
// action ini TIDAK memfilter apa pun, jadi jangan pernah kirim token,
// password, atau isi jawaban responden sebagai context.
class RecordAuditEventAction
{
    public function execute(
        string $action,
        string $result,
        ?User $actor = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        array $context = [],
        ?string $correlationId = null,
    ): AuditEvent {
        return AuditEvent::query()->create([
            'tenant_id' => $actor?->tenant_id ?? 'default',
            'actor_id' => $actor?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'result' => $result,
            'context' => $context,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'occurred_at' => now(),
        ]);
    }
}
