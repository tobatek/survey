<?php

namespace App\Console\Commands;

use App\Domains\Respondents\Models\IdempotencyKey;
use Illuminate\Console\Command;

// Fase 4 slice 6 -- pola sama persis PruneExpiredTemplateImports. Key yang
// sudah lewat expires_at (diisi SubmitResponseAction saat submit sukses,
// now()->addDay()) aman dihapus: replay lewat key yang sama setelah
// dihapus akan jatuh ke jalur ALREADY_COMPLETED biasa (status sesi sendiri
// tetap jadi penjaga utama, bukan baris idempotency_keys ini).
class PruneExpiredIdempotencyKeysCommand extends Command
{
    protected $signature = 'idempotency-keys:prune-expired';

    protected $description = 'Hapus idempotency key yang sudah lewat expires_at';

    public function handle(): int
    {
        $deleted = IdempotencyKey::query()->where('expires_at', '<', now())->delete();

        $this->info("Menghapus {$deleted} idempotency key kedaluwarsa.");

        return self::SUCCESS;
    }
}
