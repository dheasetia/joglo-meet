<?php

namespace App\Livewire;

use App\Enums\MeetingStatus;
use App\Exports\AttendanceExport;
use App\Models\MeetingParticipant;
use App\Models\MeetingType;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /** Presensi peserta terdaftar pada rapat yang sudah berjalan, dalam periode & jenis rapat terpilih. */
    protected function participations(): Builder
    {
        return MeetingParticipant::query()
            ->join('meetings', 'meetings.id', '=', 'meeting_participants.meeting_id')
            ->whereNotNull('meeting_participants.user_id')
            ->whereIn('meetings.status', [MeetingStatus::Berlangsung->value, MeetingStatus::Selesai->value, MeetingStatus::Disahkan->value])
            ->whereBetween('meetings.date', [$this->from, $this->to])
            ->when($this->type, fn ($q) => $q->where('meetings.meeting_type_id', $this->type))
            ->when($this->unit, fn ($q) => $q->whereIn('meeting_participants.user_id', User::where('unit_id', $this->unit)->select('id')));
    }

    /**
     * Rekap per orang, diurutkan dari persentase kehadiran terendah.
     *
     * @return Collection<int, object>
     */
    public function reportRows(): Collection
    {
        $rows = $this->participations()
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

        return User::query()
            ->with('unit')
            ->whereIn('id', $rows->keys())
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
    }

    /** Detail per orang per rapat (sheet kedua ekspor Excel). */
    public function detailRows(): Collection
    {
        return $this->participations()
            ->join('users', 'users.id', '=', 'meeting_participants.user_id')
            ->select([
                'users.name as user_name', 'meetings.date', 'meetings.start_time', 'meetings.title',
                'meeting_participants.attendance_status', 'meeting_participants.checked_in_at', 'meeting_participants.note',
            ])
            ->orderBy('users.name')->orderBy('meetings.date')->orderBy('meetings.start_time')
            ->get();
    }

    /** Keterangan filter yang sedang aktif (subjudul ekspor). */
    public function filterSummary(): array
    {
        return array_filter([
            'Periode '.Carbon::parse($this->from)->translatedFormat('d M Y').' – '.Carbon::parse($this->to)->translatedFormat('d M Y'),
            $this->unit ? 'Unit: '.Unit::whereKey($this->unit)->value('name') : null,
            $this->type ? 'Jenis rapat: '.MeetingType::whereKey($this->type)->value('name') : null,
        ]);
    }

    public function exportExcel(): StreamedResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return AttendanceExport::build($this->reportRows(), $this->detailRows(), $this->filterSummary())
            ->download('rekap-kehadiran-'.$this->from.'-sd-'.$this->to.'.xlsx');
    }

    public function render()
    {
        return view('livewire.attendance-report', [
            'rows' => $this->reportRows(),
            'units' => Unit::orderBy('name')->get(),
            'types' => MeetingType::orderBy('name')->get(),
        ]);
    }
}
