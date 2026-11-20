<?php

namespace App\Livewire;

use App\Enums\MeetingStatus;
use App\Models\MeetingParticipant;
use App\Models\MeetingType;
use App\Models\Unit;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Rekap Kehadiran Rapat')]
class AttendanceReport extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(except: '')]
    public string $unit = '';

    #[Url(except: '')]
    public string $type = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->from = $this->from ?: now()->startOfMonth()->format('Y-m-d');
        $this->to = $this->to ?: today()->format('Y-m-d');
    }

    public function render()
    {
        $rows = MeetingParticipant::query()
            ->join('meetings', 'meetings.id', '=', 'meeting_participants.meeting_id')
            ->whereNotNull('meeting_participants.user_id')
            ->whereIn('meetings.status', [MeetingStatus::Berlangsung->value, MeetingStatus::Selesai->value, MeetingStatus::Disahkan->value])
            ->whereBetween('meetings.date', [$this->from, $this->to])
            ->when($this->type, fn ($q) => $q->where('meetings.meeting_type_id', $this->type))
            ->selectRaw("
                meeting_participants.user_id,
                count(*) as invited,
                sum(case when attendance_status = 'hadir' then 1 else 0 end) as hadir,
                sum(case when attendance_status = 'terlambat' then 1 else 0 end) as terlambat,
                sum(case when attendance_status = 'izin' then 1 else 0 end) as izin,
                sum(case when attendance_status = 'sakit' then 1 else 0 end) as sakit,
                sum(case when attendance_status in ('alpa','belum') then 1 else 0 end) as alpa
            ")
            ->groupBy('meeting_participants.user_id')
            ->get()
            ->keyBy('user_id');

        $users = User::query()
            ->with('unit')
            ->whereIn('id', $rows->keys())
            ->when($this->unit, fn ($q) => $q->where('unit_id', $this->unit))
            ->orderBy('name')
            ->get()
            ->map(function (User $u) use ($rows) {
                $r = $rows[$u->id];
                $present = (int) $r->hadir + (int) $r->terlambat;

                return (object) [
                    'user' => $u,
                    'invited' => (int) $r->invited,
                    'hadir' => (int) $r->hadir,
                    'terlambat' => (int) $r->terlambat,
                    'izin' => (int) $r->izin,
                    'sakit' => (int) $r->sakit,
                    'alpa' => (int) $r->alpa,
                    'percent' => $r->invited ? (int) round($present / $r->invited * 100) : 0,
                ];
            })
            ->sortBy('percent')
            ->values();

        return view('livewire.attendance-report', [
            'rows' => $users,
            'units' => Unit::orderBy('name')->get(),
            'types' => MeetingType::orderBy('name')->get(),
        ]);
    }
}
