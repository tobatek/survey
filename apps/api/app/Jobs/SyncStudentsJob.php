<?php

namespace App\Jobs;

use App\Domains\Students\Actions\SyncStudentsFromPortalAction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

// FSD §18.2 queue `default`; §18.3 "roster incremental sync: harian/jam
// tertentu" — job ini dipakai baik oleh scheduler maupun trigger manual
// admin (BR-DM-005), supaya kedua jalur memakai kode yang identik.
class SyncStudentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $mode,
        private readonly ?string $triggeredByUserId = null,
    ) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('sync-students'))->expireAfter(600)];
    }

    public function handle(SyncStudentsFromPortalAction $action): void
    {
        $user = $this->triggeredByUserId !== null
            ? User::query()->find($this->triggeredByUserId)
            : null;

        $action->execute($this->mode, $user);
    }
}
