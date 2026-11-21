<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Penjadwal. Di shared hosting (cPanel) cukup satu cron job tiap menit:
|   * * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
*/

// Hapus log aktivitas yang lebih tua dari 365 hari (config/activitylog.php → clean_after_days)
Schedule::command('activitylog:clean --force')->dailyAt('02:00');

// Buat rapat dari jadwal rutin untuk beberapa hari ke depan (meeting_schedules.lead_days)
Schedule::command('rapat:buat-dari-jadwal')->dailyAt('05:00');
