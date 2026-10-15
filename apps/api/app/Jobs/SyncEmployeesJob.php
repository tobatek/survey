<?php

namespace App\Jobs;

use App\Domains\Employees\Actions\SyncEmployeesFromPortalAction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class SyncEmployeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $mode,
        private readonly ?string $triggeredByUserId = null,
    ) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('sync-employees'))->expireAfter(600)];
    }

    public function handle(SyncEmployeesFromPortalAction $action): void
    {
        $user = $this->triggeredByUserId !== null
            ? User::query()->find($this->triggeredByUserId)
            : null;

        $action->execute($this->mode, $user);
    }
}
