<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §24 (Fase 6 paket G). Jalur sukses (pg_dump sungguhan) divalidasi
// lewat restore drill manual terhadap Postgres dev asli (lihat
// docs/24_RUNBOOK_BACKUP_DR.md untuk hasil terukur) -- tidak dites di sini
// karena test suite jalan di atas SQLite (phpunit.xml) dan tidak ada jaminan
// binary pg_dump tersedia di lingkungan CI mana pun. Yang dites di sini
// murni guard logic PHP-nya.
class BackupDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function refuses_to_run_on_a_non_postgres_connection(): void
    {
        $this->artisan('backup:database')
            ->assertExitCode(1);
    }
}
