<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

// FSD §24 "PostgreSQL backup harian" (Fase 6 paket G). Pakai `pg_dump`
// format custom (`-Fc`) -- bisa direstore parsial/paralel lewat `pg_restore`,
// beda dari plain SQL dump. Disimpan di disk privat yang sama dipakai export
// (`local`, `storage/app/private`), retensi 7 hari (cukup untuk RPO harian
// + buffer investigasi, tanpa menumpuk tak terbatas di disk satu server --
// FSD §4 "1 server tanpa load balancing", ruang disk terbatas).
//
// Binary `pg_dump` diasumsikan ada di PATH server (paket `postgresql-client`
// di OS produksi) -- env PG_DUMP_BINARY cuma untuk override lokal/dev kalau
// tidak di PATH (lihat docs/24_RUNBOOK_BACKUP_DR.md).
class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Backup database PostgreSQL ke disk privat (retensi 7 hari)';

    private const RETENTION_DAYS = 7;

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if ($config['driver'] !== 'pgsql') {
            $this->error("Koneksi database aktif ({$connection}) bukan pgsql -- backup:database cuma mendukung PostgreSQL.");

            return self::FAILURE;
        }

        $filename = 'db-'.now()->format('Ymd-His').'.dump';
        $tempPath = tempnam(sys_get_temp_dir(), 'pgbackup_');

        $process = new Process([
            env('PG_DUMP_BINARY', 'pg_dump'),
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--username='.$config['username'],
            '--no-password',
            '--format=custom',
            '--file='.$tempPath,
            $config['database'],
        ]);
        $process->setTimeout(3600);
        $process->setEnv(['PGPASSWORD' => (string) $config['password']]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('pg_dump gagal: '.$process->getErrorOutput());
            @unlink($tempPath);

            return self::FAILURE;
        }

        Storage::disk('local')->putFileAs('backups', new File($tempPath), $filename);
        $size = filesize($tempPath);
        unlink($tempPath);

        $this->pruneExpired();

        $this->info("Backup selesai: backups/{$filename} ({$size} bytes).");

        return self::SUCCESS;
    }

    private function pruneExpired(): void
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS)->timestamp;

        foreach (Storage::disk('local')->files('backups') as $file) {
            if (Storage::disk('local')->lastModified($file) < $cutoff) {
                Storage::disk('local')->delete($file);
            }
        }
    }
}
