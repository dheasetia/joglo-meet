<?php

namespace App\Livewire;

use App\Enums\ScheduleFrequency;
use App\Livewire\Concerns\IndonesianValidation;
use App\Livewire\Concerns\PicksParticipants;
use App\Models\MeetingSchedule;
use App\Models\MeetingType;
use App\Models\Unit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ScheduleForm extends Component
{
    use IndonesianValidation, PicksParticipants;

    public ?int $scheduleId = null;

    public string $title = '';
    public $meeting_type_id = '';
    public $unit_id = '';
    public string $frequency = 'mingguan';
    public $day_of_week = '6';
    public $week_of_month = '1';
    public string $start_time = '08:00';
    public ?string $end_time = '';
    public ?string $location = '';
    public ?string $online_link = '';
    public $chair_id = '';
    public $secretary_id = '';
    public string $confidentiality = 'biasa';
    public string $starts_on = '';
    public ?string $ends_on = '';
    public $lead_days = 7;
    public bool $is_active = true;

    public function mount($scheduleId = null): void
    {
        $user = auth()->user();
        abort_unless($user->canCreateMeeting(), 403);

        if ($scheduleId) {
            $schedule = MeetingSchedule::findOrFail($scheduleId);
            abort_unless($schedule->canManage($user), 403);

            $this->scheduleId = $schedule->id;
            $this->fill([
                'title' => $schedule->title,
                'meeting_type_id' => (string) ($schedule->meeting_type_id ?? ''),
                'unit_id' => (string) ($schedule->unit_id ?? ''),
                'frequency' => $schedule->frequency->value,
                'day_of_week' => (string) $schedule->day_of_week,
                'week_of_month' => (string) ($schedule->week_of_month ?? 1),
                'start_time' => substr((string) $schedule->start_time, 0, 5),
                'end_time' => $schedule->end_time ? substr((string) $schedule->end_time, 0, 5) : '',
                'location' => $schedule->location ?? '',
                'online_link' => $schedule->online_link ?? '',
                'chair_id' => (string) $schedule->chair_id,
                'secretary_id' => (string) ($schedule->secretary_id ?? ''),
                'confidentiality' => $schedule->confidentiality,
                'starts_on' => $schedule->starts_on->format('Y-m-d'),
                'ends_on' => $schedule->ends_on?->format('Y-m-d') ?? '',
                'lead_days' => $schedule->lead_days,
                'is_active' => $schedule->is_active,
            ]);
            $this->participantIds = collect($schedule->participant_ids ?? [])->map(fn ($id) => (string) $id)->values()->all();

            return;
        }

        $this->starts_on = today()->format('Y-m-d');
        $this->chair_id = (string) $user->id;
        $this->unit_id = (string) ($user->unit_id ?? '');
    }

    /** Pilih jenis rapat → isi judul & unit dari jenis rapat (bila belum diisi). */
    public function updatedMeetingTypeId($value): void
    {
        $type = $value ? MeetingType::find($value) : null;
        if ($type && blank($this->title)) {
            $this->title = $type->name;
        }
        if ($type?->unit_id && ! $this->scheduleId) {
            $this->unit_id = (string) $type->unit_id;
        }
    }

    /** Pratinjau tanggal rapat berikutnya sesuai pola yang sedang diisi (tanpa menyimpan). */
    protected function preview(): array
    {
        try {
            $draft = new MeetingSchedule([
                'frequency' => $this->frequency,
                'day_of_week' => (int) $this->day_of_week,
                'week_of_month' => (int) $this->week_of_month,
                'starts_on' => $this->starts_on ?: today()->toDateString(),
                'ends_on' => $this->ends_on ?: null,
                'start_time' => $this->start_time ?: '00:00',
            ]);
            $from = $draft->firstSchedulableDay();

            return $draft->occurrencesBetween($from, $from->copy()->addYear())->take(5)->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function save()
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'meeting_type_id' => ['nullable', 'exists:meeting_types,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'frequency' => ['required', Rule::enum(ScheduleFrequency::class)],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'week_of_month' => ['nullable', 'integer', 'between:1,5'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'online_link' => ['nullable', 'url', 'max:255'],
            'chair_id' => ['required', 'exists:users,id'],
            'secretary_id' => ['nullable', 'exists:users,id'],
            'confidentiality' => ['required', Rule::in(['biasa', 'rahasia'])],
            'participantIds' => ['array'],
            'participantIds.*' => ['exists:users,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'lead_days' => ['required', 'integer', 'between:1,30'],
            'is_active' => ['boolean'],
        ], [
            'ends_on.after_or_equal' => 'Tanggal berakhir harus sama dengan atau setelah tanggal mulai.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ], [
            'title' => 'Judul rapat',
            'frequency' => 'Pola',
            'day_of_week' => 'Hari',
            'start_time' => 'Jam mulai',
            'end_time' => 'Jam selesai',
            'online_link' => 'Tautan daring',
            'chair_id' => 'Pimpinan rapat',
            'starts_on' => 'Tanggal mulai',
            'ends_on' => 'Tanggal berakhir',
            'lead_days' => 'Dibuat … hari sebelumnya',
        ]);

        $attributes = [
            'title' => $data['title'],
            'meeting_type_id' => $data['meeting_type_id'] ?: null,
            'unit_id' => $data['unit_id'] ?: null,
            'frequency' => $data['frequency'],
            'day_of_week' => (int) $data['day_of_week'],
            'week_of_month' => $data['frequency'] === ScheduleFrequency::Bulanan->value ? (int) ($data['week_of_month'] ?: 1) : null,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'] ?: null,
            'location' => $data['location'] ?: null,
            'online_link' => $data['online_link'] ?: null,
            'chair_id' => (int) $data['chair_id'],
            'secretary_id' => $data['secretary_id'] ? (int) $data['secretary_id'] : null,
            'confidentiality' => $data['confidentiality'],
            'participant_ids' => collect($data['participantIds'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'] ?: null,
            'lead_days' => (int) $data['lead_days'],
            'is_active' => (bool) $data['is_active'],
        ];

        if ($this->scheduleId) {
            $schedule = MeetingSchedule::findOrFail($this->scheduleId);
            abort_unless($schedule->canManage(auth()->user()), 403);
            $schedule->update($attributes);
        } else {
            $schedule = MeetingSchedule::create($attributes + ['created_by' => auth()->id()]);
        }

        // Langsung buat rapat yang jatuh dalam masa "dibuat … hari sebelumnya"
        $created = $schedule->generateUpcoming();

        session()->flash('toast', 'Jadwal rutin tersimpan.'.($created->isNotEmpty() ? " {$created->count()} rapat terjadwal dibuat." : ''));

        return $this->redirectRoute('schedules.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.schedule-form', [
            ...$this->participantPickerData(),
            'types' => MeetingType::where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'preview' => $this->preview(),
            'days' => MeetingSchedule::DAYS,
            'weeks' => MeetingSchedule::WEEKS,
            'frequencies' => ScheduleFrequency::options(),
        ])->title($this->scheduleId ? 'Ubah Jadwal Rutin' : 'Jadwal Rutin Baru');
    }
}
