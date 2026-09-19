<?php

use App\Jobs\SyncEmployeesJob;
use App\Jobs\SyncStudentsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FSD §18.3 "roster incremental sync: harian/jam tertentu". withoutOverlapping
// tambahan di sini adalah pengaman kedua di atas WithoutOverlapping milik job
// itu sendiri (App\Jobs\SyncStudentsJob/SyncEmployeesJob).
Schedule::job(new SyncStudentsJob('scheduled'))->dailyAt('02:00')->withoutOverlapping();
Schedule::job(new SyncEmployeesJob('scheduled'))->dailyAt('02:15')->withoutOverlapping();

// FSD §15.4 — staging template_imports expired (TTL 1 jam, lihat
// ValidateTemplateImportAction) dibersihkan tiap jam.
Schedule::command('template-imports:prune-expired')->hourly()->withoutOverlapping();

// FSD §10.4/§18.3 — aktivasi & penutupan campaign otomatis berdasar
// starts_at/ends_at, dicek tiap menit (atomic lock ada di dalam
// ActivateCampaignAction/TransitionCampaignStatusAction sendiri per
// campaign; withoutOverlapping di sini cuma cegah dua proses command yang
// sama berjalan bersamaan).
Schedule::command('campaigns:activate-due')->everyMinute()->withoutOverlapping();
Schedule::command('campaigns:close-expired')->everyMinute()->withoutOverlapping();

// FSD §10.5 — reminder, jarak minimal & jam tenang dicek di dalam action
// tiap kali command ini jalan, jadi aman dijadwalkan tiap jam.
Schedule::command('campaigns:send-reminders')->hourly()->withoutOverlapping();
