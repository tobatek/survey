<?php

namespace App\Console\Commands;

use App\Domains\Questionnaires\Models\TemplateImport;
use Illuminate\Console\Command;

// FSD §15.4 "staging record memiliki expiry" — staging yang sudah lewat
// expires_at dan belum pernah di-commit dihapus permanen (bukan cuma
// ditandai) karena tidak ada kebutuhan audit jangka panjang untuk staging
// yang tidak jadi dipakai; audit trail resmi cuma dicatat saat commit
// berhasil (lihat CommitTemplateImportAction -> RecordAuditEventAction).
class PruneExpiredTemplateImports extends Command
{
    protected $signature = 'template-imports:prune-expired';

    protected $description = 'Hapus staging template_imports yang sudah expired dan belum di-commit';

    public function handle(): int
    {
        $deleted = TemplateImport::query()
            ->whereNull('committed_at')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Menghapus {$deleted} staging import kedaluwarsa.");

        return self::SUCCESS;
    }
}
