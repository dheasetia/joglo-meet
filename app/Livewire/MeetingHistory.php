<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Meeting;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tab "Riwayat" di Ruang Rapat: linimasa log aktivitas satu rapat.
 * Komponen terpisah & dimuat lazy agar autosave notulen tidak ikut memuat ulang log.
 */
class MeetingHistory extends Component
{
    #[Locked]
    public int $meetingId;

    public int $limit = 50;

    public function mount(int $meetingId): void
    {
        $this->meetingId = $meetingId;
        $this->authorizeView();
    }

    protected function authorizeView(): void
    {
        $meeting = Meeting::query()->select(['id', 'chair_id', 'secretary_id', 'created_by'])->findOrFail($this->meetingId);
        abort_unless($meeting->canManage(auth()->user()), 403);
    }

    /** Dipanggil saat tab "Riwayat" diklik agar log terbaru langsung tampil. */
    #[On('meeting-history-refresh')]
    public function refreshHistory(): void
    {
        $this->authorizeView();
    }

    public function loadMore(): void
    {
        $this->limit += 50;
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="card space-y-3 p-5">
            <div class="h-4 w-40 animate-pulse rounded bg-slate-200"></div>
            <div class="h-3 w-full animate-pulse rounded bg-slate-100"></div>
            <div class="h-3 w-5/6 animate-pulse rounded bg-slate-100"></div>
            <div class="h-3 w-2/3 animate-pulse rounded bg-slate-100"></div>
        </div>
        HTML;
    }

    public function render()
    {
        $logs = ActivityLog::query()
            ->forMeeting($this->meetingId)
            ->with('causer')
            ->latest('id')
            ->limit($this->limit + 1)
            ->get();

        $hasMore = $logs->count() > $this->limit;

        $days = $logs->take($this->limit)->groupBy(function (ActivityLog $log) {
            $date = $log->created_at->copy()->startOfDay();

            return match (true) {
                $date->isToday() => 'Hari ini',
                $date->isYesterday() => 'Kemarin',
                default => $date->translatedFormat('l, d F Y'),
            };
        });

        return view('livewire.meeting-history', ['days' => $days, 'hasMore' => $hasMore]);
    }
}
