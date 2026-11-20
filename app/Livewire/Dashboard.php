<?php

namespace App\Livewire;

use App\Enums\MeetingStatus;
use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();

        $meetings = Meeting::query()->visibleTo($user);
        $actions = ActionItem::query()->visibleTo($user);

        $monthMeetingIds = (clone $meetings)
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
            ->where('status', '!=', MeetingStatus::Batal->value)
            ->pluck('id');

        $attendance = MeetingParticipant::query()
            ->whereIn('meeting_id', $monthMeetingIds)
            ->where('attendance_status', '!=', 'belum')
            ->selectRaw("count(*) as total, sum(case when attendance_status in ('hadir','terlambat') then 1 else 0 end) as present")
            ->first();

        return view('livewire.dashboard', [
            'stats' => [
                'meetings' => $monthMeetingIds->count(),
                'attendance' => $attendance && $attendance->total ? (int) round($attendance->present / $attendance->total * 100) : null,
                'open' => (clone $actions)->open()->count(),
                'overdue' => (clone $actions)->overdue()->count(),
            ],
            'overdue' => (clone $actions)->overdue()->with(['pic', 'meeting'])->orderBy('due_date')->limit(8)->get(),
            'dueSoon' => (clone $actions)->open()
                ->whereBetween('due_date', [today(), today()->addDays(7)])
                ->with(['pic', 'meeting'])->orderBy('due_date')->limit(8)->get(),
            'upcoming' => (clone $meetings)
                ->whereIn('status', [MeetingStatus::Terjadwal->value, MeetingStatus::Berlangsung->value])
                ->whereDate('date', '>=', today())
                ->with(['type', 'chair'])->withCount('participants')
                ->orderBy('date')->orderBy('start_time')->limit(5)->get(),
            'pendingApproval' => (clone $meetings)
                ->where('status', MeetingStatus::Selesai->value)
                ->with('chair')->orderByDesc('date')->limit(5)->get(),
            'myTasks' => ActionItem::query()->where('pic_id', $user->id)->open()->count(),
        ]);
    }
}
