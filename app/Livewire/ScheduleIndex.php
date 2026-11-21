<?php

namespace App\Livewire;

use App\Models\Meeting;
use App\Models\MeetingSchedule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Jadwal Rapat Rutin')]
class ScheduleIndex extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->canCreateMeeting(), 403);
    }

    protected function schedule(int $id): MeetingSchedule
    {
        $schedule = MeetingSchedule::findOrFail($id);
        abort_unless($schedule->canManage(auth()->user()), 403);

        return $schedule;
    }

    public function toggleActive(int $id): void
    {
        $schedule = $this->schedule($id);
        $schedule->update(['is_active' => ! $schedule->is_active]);
        $created = $schedule->generateUpcoming();

        $this->dispatch('toast', message: $schedule->is_active
            ? 'Jadwal diaktifkan.'.($created->isNotEmpty() ? " {$created->count()} rapat terjadwal dibuat." : '')
            : 'Jadwal dinonaktifkan. Rapat yang sudah dibuat tetap ada.');
    }

    public function delete(int $id): void
    {
        $this->schedule($id)->delete();
        $this->dispatch('toast', message: 'Jadwal dihapus. Rapat yang sudah dibuat tetap ada.');
    }

    public function render()
    {
        $user = auth()->user();
        $schedules = MeetingSchedule::query()
            ->manageableBy($user)
            ->with(['type', 'unit', 'chair', 'secretary'])
            ->withCount(['meetings', 'meetings as upcoming_count' => fn ($q) => $q->whereDate('date', '>=', today())])
            ->orderByDesc('is_active')->orderBy('title')
            ->get();

        $upcoming = Meeting::query()
            ->whereIn('meeting_schedule_id', $schedules->pluck('id'))
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->get(['id', 'meeting_schedule_id', 'date', 'status'])
            ->groupBy('meeting_schedule_id');

        return view('livewire.schedule-index', compact('schedules', 'upcoming'));
    }
}
