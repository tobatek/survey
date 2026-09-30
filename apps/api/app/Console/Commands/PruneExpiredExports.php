<?php

namespace App\Console\Commands;

use App\Domains\Analytics\Models\ExportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

// FSD §14.2 langkah 8 -- file export privat & job row dibersihkan setelah
// expires_at lewat, untuk status apa pun (termasuk queued/processing yang
// nyangkut karena tidak pernah mencapai status terminal -- lihat fallback
// expiry di ExportJob::MAX_PROCESSING_WINDOW_HOURS). Hard-delete (bukan
// soft/expired-state) mengikuti pola template-imports:prune-expired --
// tidak ada kebutuhan audit trail jangka panjang yang disebut FSD untuk
// export_jobs (keputusan scope slice 5).
class PruneExpiredExports extends Command
{
    protected $signature = 'exports:prune-expired';

    protected $description = 'Hapus file dan baris export_jobs yang sudah lewat expires_at';

    public function handle(): int
    {
        $deleted = 0;

        ExportJob::query()
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($jobs) use (&$deleted) {
                foreach ($jobs as $job) {
                    if ($job->file_path !== null) {
                        Storage::disk('local')->delete($job->file_path);
                    }

                    $job->delete();
                    $deleted++;
                }
            });

        $this->info("Menghapus {$deleted} export job kedaluwarsa.");

        return self::SUCCESS;
    }
}
