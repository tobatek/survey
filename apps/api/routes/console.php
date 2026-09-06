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
