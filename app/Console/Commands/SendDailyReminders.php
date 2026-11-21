<?php

namespace App\Console\Commands;

use App\Enums\MeetingStatus;
use App\Models\ActionItem;
use App\Models\Meeting;
use App\Services\WhatsApp\WhatsAppNotifier;
use Illuminate\Console\Command;

/**
 * Pengingat harian via WhatsApp (jam: config whatsapp.reminder_time):
 * rapat besok, RTL yang deadline-nya besok, dan RTL yang kemarin melewati deadline.
 * Aman dijalankan ulang: setiap pengingat hanya sekali (dedupe_key).
 */
class SendDailyReminders extends Command
{
    protected $signature = 'rapat:kirim-pengingat';

    protected $description = 'Antrekan pengingat WhatsApp: rapat besok, deadline RTL besok, RTL terlambat';

    public function handle(WhatsAppNotifier $notifier): int
    {
        $tomorrow = today()->addDay();
        $counts = ['rapat' => 0, 'rtl_h1' => 0, 'rtl_terlambat' => 0];

        Meeting::query()->where('status', MeetingStatus::Terjadwal->value)->whereDate('date', $tomorrow)->with('chair')
            ->each(function (Meeting $meeting) use ($notifier, &$counts) {
                $counts['rapat'] += $notifier->meetingReminder($meeting)['antre'];
            });

        ActionItem::query()->open()->whereDate('due_date', $tomorrow)->with(['pic', 'meeting'])
            ->each(function (ActionItem $item) use ($notifier, &$counts) {
                $counts['rtl_h1'] += $notifier->actionDueTomorrow($item)['antre'];
            });

        // Sekali saja, sehari setelah deadline terlewat
        ActionItem::query()->open()->whereDate('due_date', today()->subDay())->with(['pic', 'meeting'])
            ->each(function (ActionItem $item) use ($notifier, &$counts) {
                $counts['rtl_terlambat'] += $notifier->actionOverdue($item)['antre'];
            });

        $this->info("Diantrekan: {$counts['rapat']} pengingat rapat, {$counts['rtl_h1']} deadline RTL besok, {$counts['rtl_terlambat']} RTL terlambat.");

        return self::SUCCESS;
    }
}
