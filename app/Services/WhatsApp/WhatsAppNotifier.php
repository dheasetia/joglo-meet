<?php

namespace App\Services\WhatsApp;

use App\Enums\MeetingStatus;
use App\Jobs\SendWhatsappMessage;
use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\MeetingSignature;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Satu-satunya pintu untuk mengantrekan notifikasi WhatsApp:
 * nomor dinormalkan, pesan ganda dicegah (dedupe_key), penerima tanpa nomor dicatat "dilewati",
 * dan pengiriman diberi jeda acak antarpesan.
 */
class WhatsAppNotifier
{
    /** Ringkasan hasil antre: ['antre' => n, 'dilewati' => n, 'sudah' => n] */
    protected array $summary = [];

    public function queue(?User $user, string $type, string $message, ?string $dedupeKey = null, ?int $meetingId = null, ?int $actionItemId = null, ?string $phone = null): ?WhatsappMessage
    {
        if ($dedupeKey && WhatsappMessage::where('dedupe_key', $dedupeKey)->exists()) {
            $this->count('sudah');

            return null;
        }

        $normalized = Phone::normalize($phone ?? $user?->phone);
        $skipReason = match (true) {
            $user && ! $user->is_active => 'Pengguna nonaktif',
            ! $normalized => 'Belum ada No. WhatsApp yang valid',
            default => null,
        };

        try {
            $record = WhatsappMessage::create([
                'user_id' => $user?->id,
                'phone' => $normalized,
                'type' => $type,
                'meeting_id' => $meetingId,
                'action_item_id' => $actionItemId,
                'message' => $message,
                'status' => $skipReason ? WhatsappMessage::SKIPPED : WhatsappMessage::QUEUED,
                'error' => $skipReason,
                'dedupe_key' => $dedupeKey,
                'created_by' => auth()->id(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->count('sudah');

            return null; // diantrekan proses lain pada saat bersamaan
        }

        if ($skipReason) {
            $this->count('dilewati');

            return $record;
        }

        SendWhatsappMessage::dispatch($record->id)->delay($this->nextSlot());
        $this->count('antre');

        return $record;
    }

    /** Antrekan ulang pesan gagal/dilewati (mis. setelah nomor pengguna diperbaiki). */
    public function retry(WhatsappMessage $message): bool
    {
        $phone = Phone::normalize($message->user?->phone ?? $message->phone);
        if (! $phone) {
            $message->update(['status' => WhatsappMessage::SKIPPED, 'error' => 'Belum ada No. WhatsApp yang valid']);

            return false;
        }

        $message->update(['phone' => $phone, 'status' => WhatsappMessage::QUEUED, 'error' => null, 'attempts' => 0]);
        SendWhatsappMessage::dispatch($message->id)->delay($this->nextSlot());

        return true;
    }

    /** Jadwal kirim berikutnya: setelah pesan terakhir + jeda acak (agar tidak dianggap spam). */
    protected function nextSlot(): \DateTimeInterface
    {
        $gap = random_int((int) config('whatsapp.delay_min'), max((int) config('whatsapp.delay_min'), (int) config('whatsapp.delay_max')));
        $slot = max(now()->getTimestamp(), (int) Cache::get('whatsapp:next-slot', 0)) + $gap;
        Cache::put('whatsapp:next-slot', $slot, now()->addHour());

        return now()->setTimestamp($slot);
    }

    protected function count(string $key): void
    {
        $this->summary[$key] = ($this->summary[$key] ?? 0) + 1;
    }

    /** Ambil & kosongkan ringkasan antre (untuk pesan toast). */
    public function pullSummary(): array
    {
        [$summary, $this->summary] = [$this->summary + ['antre' => 0, 'dilewati' => 0, 'sudah' => 0], []];

        return $summary;
    }

    // ---------------------------------------------------------------- Rapat

    /** Peserta terdaftar yang aktif, tanpa $except (mis. orang yang menekan tombol). */
    protected function recipients(Meeting $meeting, ?int $except = null): Collection
    {
        return $meeting->participants()->whereNotNull('user_id')->with('user')->get()
            ->pluck('user')->filter()->filter(fn (User $u) => $u->is_active && $u->id !== $except)->values();
    }

    /** Pengguna yang sudah pernah diantrekan/dikirimi undangan rapat ini. */
    public function invitedUserIds(Meeting $meeting): Collection
    {
        return WhatsappMessage::where('meeting_id', $meeting->id)->where('type', 'undangan')
            ->whereIn('status', [WhatsappMessage::QUEUED, WhatsappMessage::SENT])->pluck('user_id');
    }

    public function hasInvited(Meeting $meeting): bool
    {
        return $this->invitedUserIds($meeting)->isNotEmpty();
    }

    /** Undangan ke semua peserta yang belum diundang (aman dipanggil ulang: peserta baru saja yang dikirimi). */
    public function meetingInvitation(Meeting $meeting, ?int $except = null): array
    {
        $meeting->loadMissing(['chair', 'agendas']);
        foreach ($this->recipients($meeting, $except) as $user) {
            $this->queue($user, 'undangan', Templates::invitation($meeting, $user), "undangan:{$meeting->id}:{$user->id}", $meeting->id);
        }

        return $this->pullSummary();
    }

    /** @param  array<string, array{0:string,1:string}>  $changes */
    public function meetingChanged(Meeting $meeting, array $changes, ?int $except = null): array
    {
        $invited = $this->invitedUserIds($meeting);
        $hash = substr(md5(json_encode($changes)), 0, 12);
        foreach ($this->recipients($meeting, $except)->whereIn('id', $invited) as $user) {
            $this->queue($user, 'perubahan', Templates::changed($meeting, $user, $changes), "perubahan:{$meeting->id}:{$user->id}:{$hash}", $meeting->id);
        }

        return $this->pullSummary();
    }

    public function meetingCancelled(Meeting $meeting, ?int $except = null): array
    {
        $invited = $this->invitedUserIds($meeting);
        foreach ($this->recipients($meeting, $except)->whereIn('id', $invited) as $user) {
            $this->queue($user, 'pembatalan', Templates::cancelled($meeting, $user), "pembatalan:{$meeting->id}:{$user->id}", $meeting->id);
        }

        return $this->pullSummary();
    }

    public function meetingReminder(Meeting $meeting): array
    {
        if ($meeting->status !== MeetingStatus::Terjadwal) {
            return $this->pullSummary();
        }
        foreach ($this->recipients($meeting) as $user) {
            $this->queue($user, 'pengingat_rapat', Templates::reminder($meeting, $user), "pengingat:{$meeting->id}:{$user->id}:{$meeting->date->toDateString()}", $meeting->id);
        }

        return $this->pullSummary();
    }

    public function minutesApproved(Meeting $meeting, ?MeetingSignature $signature): array
    {
        $meeting->loadMissing('approver');
        foreach ($this->recipients($meeting) as $user) {
            $key = "disahkan:{$meeting->id}:{$user->id}:".($signature?->code ?? 'tanpa-kode');
            $this->queue($user, 'notulen_disahkan', Templates::approved($meeting, $user, $signature), $key, $meeting->id);
        }

        return $this->pullSummary();
    }

    // ---------------------------------------------------------------- RTL

    public function actionAssigned(ActionItem $item, ?int $except = null): array
    {
        $item->loadMissing(['pic', 'meeting']);
        if ($item->pic && $item->pic_id !== $except) {
            $this->queue($item->pic, 'rtl_baru', Templates::actionAssigned($item, $item->pic), "rtl-baru:{$item->id}:{$item->pic_id}", $item->meeting_id, $item->id);
        }

        return $this->pullSummary();
    }

    public function actionDueTomorrow(ActionItem $item): array
    {
        $item->loadMissing('pic');
        if ($item->pic) {
            $this->queue($item->pic, 'rtl_h1', Templates::actionDueTomorrow($item, $item->pic), "rtl-h1:{$item->id}:{$item->due_date->toDateString()}", $item->meeting_id, $item->id);
        }

        return $this->pullSummary();
    }

    public function actionOverdue(ActionItem $item): array
    {
        $item->loadMissing('pic');
        if ($item->pic) {
            $this->queue($item->pic, 'rtl_terlambat', Templates::actionOverdue($item, $item->pic), "rtl-terlambat:{$item->id}:{$item->due_date->toDateString()}", $item->meeting_id, $item->id);
        }

        return $this->pullSummary();
    }
}
