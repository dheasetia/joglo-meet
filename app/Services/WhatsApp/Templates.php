<?php

namespace App\Services\WhatsApp;

use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\MeetingSchedule;
use App\Models\MeetingSignature;
use App\Models\User;

/**
 * Isi pesan WhatsApp (format WA: *tebal*, _miring_).
 * Tautan dibuat dari APP_URL — pastikan sesuai domain server agar bisa dibuka dari HP.
 */
class Templates
{
    protected static function greeting(?User $user): string
    {
        return "Assalamu'alaikum warahmatullahi wabarakatuh".($user ? ", {$user->name}." : '.');
    }

    protected static function footer(): string
    {
        return "Jazakumullahu khairan.\n_— Sekretariat ".config('rapat.institution').'_';
    }

    protected static function when(Meeting $meeting): string
    {
        $day = MeetingSchedule::DAYS[$meeting->date->dayOfWeekIso];

        return "📅 {$day}, ".$meeting->date->translatedFormat('d F Y')."\n🕐 ".$meeting->timeRange().' WIB';
    }

    protected static function where(Meeting $meeting): string
    {
        return collect([
            $meeting->location ? "📍 {$meeting->location}" : null,
            $meeting->online_link ? "🔗 {$meeting->online_link}" : null,
        ])->filter()->implode("\n");
    }

    public static function invitation(Meeting $meeting, User $user): string
    {
        $agendas = $meeting->agendas->pluck('title')->map(fn ($t, $i) => ($i + 1).'. '.$t)->implode("\n");

        return implode("\n\n", array_filter([
            static::greeting($user),
            "Dengan hormat, Bapak/Ibu diundang untuk menghadiri:\n*{$meeting->title}*\n".static::when($meeting)
                .(static::where($meeting) ? "\n".static::where($meeting) : '')
                ."\n👤 Pimpinan rapat: ".$meeting->chair?->name,
            $agendas ? "*Agenda:*\n{$agendas}" : null,
            'Detail & presensi: '.route('meetings.show', $meeting),
            static::footer(),
        ]));
    }

    /** @param  array<string, array{0:string,1:string}>  $changes  [label => [lama, baru]] */
    public static function changed(Meeting $meeting, User $user, array $changes): string
    {
        $lines = collect($changes)->map(fn ($c, $label) => "• {$label}: ~{$c[0]}~ → *{$c[1]}*")->implode("\n");

        return implode("\n\n", [
            static::greeting($user),
            "Ada *perubahan* pada rapat *{$meeting->title}*:\n{$lines}",
            "Jadwal terbaru:\n".static::when($meeting).(static::where($meeting) ? "\n".static::where($meeting) : ''),
            'Detail: '.route('meetings.show', $meeting),
            static::footer(),
        ]);
    }

    public static function cancelled(Meeting $meeting, User $user): string
    {
        return implode("\n\n", [
            static::greeting($user),
            "Rapat *{$meeting->title}* yang dijadwalkan ".MeetingSchedule::DAYS[$meeting->date->dayOfWeekIso].', '
                .$meeting->date->translatedFormat('d F Y').' pukul '.substr((string) $meeting->start_time, 0, 5).' WIB *dibatalkan*.',
            'Mohon maaf atas perubahan ini.',
            static::footer(),
        ]);
    }

    public static function reminder(Meeting $meeting, User $user): string
    {
        return implode("\n\n", array_filter([
            static::greeting($user),
            "⏰ *Pengingat rapat besok*\n*{$meeting->title}*\n".static::when($meeting)
                .(static::where($meeting) ? "\n".static::where($meeting) : ''),
            'Detail & presensi: '.route('meetings.show', $meeting),
            static::footer(),
        ]));
    }

    public static function approved(Meeting $meeting, User $user, ?MeetingSignature $signature): string
    {
        return implode("\n\n", array_filter([
            static::greeting($user),
            "Notulen rapat *{$meeting->title}* (".$meeting->date->translatedFormat('d F Y').') telah *disahkan*'
                .($meeting->approver ? ' oleh '.$meeting->approver->name : '').'.',
            'Baca notulen & keputusan: '.route('meetings.show', $meeting),
            $signature ? "Kode verifikasi: *{$signature->code}*\n".$signature->url() : null,
            static::footer(),
        ]));
    }

    public static function actionAssigned(ActionItem $item, User $user): string
    {
        return implode("\n\n", array_filter([
            static::greeting($user),
            "Bapak/Ibu ditunjuk sebagai *penanggung jawab* rencana tindak lanjut (RTL):\n*{$item->title}*",
            "Dari rapat: {$item->meeting->title}\n⏳ Deadline: ".$item->due_date->translatedFormat('d F Y')
                ."\nPrioritas: ".$item->priority->label()
                .($item->success_indicator ? "\n🎯 Indikator selesai: {$item->success_indicator}" : ''),
            'Perbarui progres di: '.route('actions.mine'),
            static::footer(),
        ]));
    }

    public static function actionDueTomorrow(ActionItem $item, User $user): string
    {
        return implode("\n\n", [
            static::greeting($user),
            "⏳ *Pengingat deadline RTL besok* (".$item->due_date->translatedFormat('d F Y')."):\n*{$item->title}*\nProgres saat ini: {$item->progress}% ({$item->status->label()})",
            'Perbarui progres di: '.route('actions.mine'),
            static::footer(),
        ]);
    }

    public static function actionOverdue(ActionItem $item, User $user): string
    {
        return implode("\n\n", [
            static::greeting($user),
            "⚠️ RTL berikut *melewati deadline* (".$item->due_date->translatedFormat('d F Y')."):\n*{$item->title}*\nProgres saat ini: {$item->progress}% ({$item->status->label()})",
            'Mohon perbarui progres atau sampaikan kendalanya di: '.route('actions.mine'),
            static::footer(),
        ]);
    }
}
