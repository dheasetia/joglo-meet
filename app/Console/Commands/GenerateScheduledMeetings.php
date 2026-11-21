<?php

namespace App\Console\Commands;

use App\Models\MeetingSchedule;
use Illuminate\Console\Command;

/** Dijalankan penjadwal tiap pagi: buat rapat dari jadwal rutin untuk beberapa hari ke depan. */
class GenerateScheduledMeetings extends Command
{
    protected $signature = 'rapat:buat-dari-jadwal';

    protected $description = 'Buat rapat terjadwal dari jadwal rapat rutin yang aktif';

    public function handle(): int
    {
        $total = 0;

        MeetingSchedule::query()->where('is_active', true)->with('type')->orderBy('id')->each(function (MeetingSchedule $schedule) use (&$total) {
            foreach ($schedule->generateUpcoming() as $meeting) {
                $this->line("✓ {$meeting->title}");
                $total++;
            }
        });

        $this->info($total ? "{$total} rapat dibuat dari jadwal rutin." : 'Tidak ada rapat baru yang perlu dibuat.');

        return self::SUCCESS;
    }
}
