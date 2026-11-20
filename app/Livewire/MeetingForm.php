<?php

namespace App\Livewire;

use App\Enums\MeetingStatus;
use App\Livewire\Concerns\IndonesianValidation;
use App\Models\Meeting;
use App\Models\MeetingType;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MeetingForm extends Component
{
    use IndonesianValidation;

    public ?int $meetingId = null;

    public string $title = '';
    public $meeting_type_id = '';
    public $unit_id = '';
    public string $date = '';
    public string $start_time = '';
    public ?string $end_time = '';
    public ?string $location = '';
    public ?string $online_link = '';
    public $chair_id = '';
    public $secretary_id = '';
    public string $confidentiality = 'biasa';

    /** @var array<int, string> */
    public array $participantIds = [];

    /** @var array<int, array{id:?int,title:string,presenter_id:mixed,duration_minutes:mixed,is_carry_over:bool}> */
    public array $agendas = [];

    public string $participantSearch = '';

    public function mount($meetingId = null): void
    {
        $user = auth()->user();

        if ($meetingId) {
            $meeting = Meeting::with(['participants', 'agendas'])->findOrFail($meetingId);
            abort_unless($meeting->canManage($user), 403);
            abort_if($meeting->isLocked(), 403, 'Rapat yang sudah disahkan tidak dapat diubah.');

            $this->meetingId = $meeting->id;
            $this->fill([
                'title' => $meeting->title,
                'meeting_type_id' => (string) ($meeting->meeting_type_id ?? ''),
                'unit_id' => (string) ($meeting->unit_id ?? ''),
                'date' => $meeting->date->format('Y-m-d'),
                'start_time' => substr($meeting->start_time, 0, 5),
                'end_time' => $meeting->end_time ? substr($meeting->end_time, 0, 5) : '',
                'location' => $meeting->location ?? '',
                'online_link' => $meeting->online_link ?? '',
                'chair_id' => (string) $meeting->chair_id,
                'secretary_id' => (string) ($meeting->secretary_id ?? ''),
                'confidentiality' => $meeting->confidentiality,
            ]);
            $this->participantIds = $meeting->participants->whereNotNull('user_id')->pluck('user_id')->map(fn ($id) => (string) $id)->values()->all();
            $this->agendas = $meeting->agendas->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'presenter_id' => (string) ($a->presenter_id ?? ''),
                'duration_minutes' => $a->duration_minutes,
                'is_carry_over' => $a->is_carry_over,
            ])->all();

            return;
        }

        abort_unless($user->canCreateMeeting(), 403);

        $this->date = today()->format('Y-m-d');
        $this->start_time = '08:00';
        $this->chair_id = (string) $user->id;
        $this->unit_id = (string) ($user->unit_id ?? '');
        $this->agendas = $this->defaultAgendas(null);
    }

    /** Saat jenis rapat dipilih pada rapat baru: isi unit & agenda dari template. */
    public function updatedMeetingTypeId($value): void
    {
        $type = $value ? MeetingType::find($value) : null;

        if ($type?->unit_id && ! $this->meetingId) {
            $this->unit_id = (string) $type->unit_id;
        }

        $untouched = collect($this->agendas)->every(fn ($a) => empty($a['id']));
        if (! $this->meetingId && $untouched) {
            $this->agendas = $this->defaultAgendas($type);
        }

        if ($type && blank($this->title)) {
            $this->title = $type->name;
        }
    }

    public function applyTemplate(): void
    {
        $type = $this->meeting_type_id ? MeetingType::find($this->meeting_type_id) : null;
        $existing = collect($this->agendas)->filter(fn ($a) => ! empty($a['id']));
        $this->agendas = $existing->isEmpty() ? $this->defaultAgendas($type) : $this->agendas;
    }

    protected function defaultAgendas(?MeetingType $type): array
    {
        $titles = array_merge(
            config('rapat.opening_agendas', []),
            [config('rapat.carry_over_agenda')],
            $type?->default_agendas ?? [''],
            config('rapat.closing_agendas', []),
        );

        return collect($titles)->map(fn ($t) => [
            'id' => null,
            'title' => (string) $t,
            'presenter_id' => '',
            'duration_minutes' => null,
            'is_carry_over' => $t === config('rapat.carry_over_agenda'),
        ])->values()->all();
    }

    public function addAgenda(): void
    {
        // Sisipkan sebelum agenda penutup ("Lain-lain", "Penutup …") bila ada
        $closing = config('rapat.closing_agendas', []);
        $index = collect($this->agendas)->search(fn ($a) => in_array($a['title'], $closing, true));
        $new = ['id' => null, 'title' => '', 'presenter_id' => '', 'duration_minutes' => null, 'is_carry_over' => false];

        if ($index === false) {
            $this->agendas[] = $new;
        } else {
            array_splice($this->agendas, $index, 0, [$new]);
        }
    }

    public function removeAgenda(int $index): void
    {
        unset($this->agendas[$index]);
        $this->agendas = array_values($this->agendas);
    }

    public function moveAgenda(int $index, int $direction): void
    {
        $target = $index + $direction;
        if (! isset($this->agendas[$index], $this->agendas[$target])) {
            return;
        }
        [$this->agendas[$index], $this->agendas[$target]] = [$this->agendas[$target], $this->agendas[$index]];
    }

    public function toggleUnit(?int $unitId): void
    {
        $ids = User::where('is_active', true)->where('unit_id', $unitId)->pluck('id')->map(fn ($id) => (string) $id);
        $current = collect($this->participantIds);

        $this->participantIds = $ids->diff($current)->isEmpty()
            ? $current->diff($ids)->values()->all()   // semua sudah terpilih → batalkan
            : $current->merge($ids)->unique()->values()->all();
    }

    public function save()
    {
        $this->agendas = collect($this->agendas)->filter(fn ($a) => filled(trim($a['title'] ?? '')))->values()->all();

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'meeting_type_id' => ['nullable', 'exists:meeting_types,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'online_link' => ['nullable', 'url', 'max:255'],
            'chair_id' => ['required', 'exists:users,id'],
            'secretary_id' => ['nullable', 'exists:users,id'],
            'confidentiality' => ['required', Rule::in(['biasa', 'rahasia'])],
            'participantIds' => ['array'],
            'participantIds.*' => ['exists:users,id'],
            'agendas' => ['required', 'array', 'min:1'],
            'agendas.*.title' => ['required', 'string', 'max:255'],
            'agendas.*.presenter_id' => ['nullable', 'exists:users,id'],
            'agendas.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
        ], [], [
            'title' => 'Judul rapat',
            'date' => 'Tanggal',
            'start_time' => 'Jam mulai',
            'end_time' => 'Jam selesai',
            'online_link' => 'Tautan daring',
            'chair_id' => 'Pimpinan rapat',
            'agendas' => 'Agenda',
            'agendas.*.title' => 'Judul agenda',
            'agendas.*.duration_minutes' => 'Durasi',
        ]);

        $user = auth()->user();

        $meeting = DB::transaction(function () use ($data, $user) {
            $attributes = [
                'title' => $data['title'],
                'meeting_type_id' => $data['meeting_type_id'] ?: null,
                'unit_id' => $data['unit_id'] ?: null,
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'] ?: null,
                'location' => $data['location'] ?: null,
                'online_link' => $data['online_link'] ?: null,
                'chair_id' => $data['chair_id'],
                'secretary_id' => $data['secretary_id'] ?: null,
                'confidentiality' => $data['confidentiality'],
            ];

            if ($this->meetingId) {
                $meeting = Meeting::findOrFail($this->meetingId);
                abort_unless($meeting->canManage($user) && ! $meeting->isLocked(), 403);
                $meeting->update($attributes);
            } else {
                $meeting = Meeting::create($attributes + [
                    'status' => MeetingStatus::Terjadwal,
                    'created_by' => $user->id,
                ]);
            }

            // Peserta: pimpinan & notulis otomatis termasuk
            $ids = collect($data['participantIds'] ?? [])
                ->push($attributes['chair_id'], $attributes['secretary_id'])
                ->filter()->map(fn ($id) => (int) $id)->unique();

            $existing = $meeting->participants()->whereNotNull('user_id')->pluck('user_id');
            foreach ($ids->diff($existing) as $id) {
                $meeting->participants()->create(['user_id' => $id]);
            }
            // Hapus yang dibatalkan undangannya, kecuali yang sudah tercatat presensinya
            $meeting->participants()
                ->whereNotNull('user_id')
                ->whereNotIn('user_id', $ids->all())
                ->where('attendance_status', 'belum')
                ->delete();

            // Agenda
            $keepIds = [];
            foreach (array_values($data['agendas']) as $i => $row) {
                $values = [
                    'sort_order' => $i + 1,
                    'title' => $row['title'],
                    'presenter_id' => ($row['presenter_id'] ?? '') ?: null,
                    'duration_minutes' => ($row['duration_minutes'] ?? '') ?: null,
                    'is_carry_over' => (bool) ($this->agendas[$i]['is_carry_over'] ?? false),
                ];
                $agendaId = $this->agendas[$i]['id'] ?? null;
                $agenda = $agendaId ? $meeting->agendas()->whereKey($agendaId)->first() : null;
                if ($agenda) {
                    $agenda->update($values);
                } else {
                    $agenda = $meeting->agendas()->create($values);
                }
                $keepIds[] = $agenda->id;
            }
            $meeting->agendas()->whereNotIn('id', $keepIds)->get()->each->delete();

            return $meeting;
        });

        session()->flash('toast', $this->meetingId ? 'Perubahan rapat tersimpan.' : 'Rapat berhasil dibuat.');

        return $this->redirectRoute('meetings.show', ['meeting' => $meeting->id], navigate: true);
    }

    public function render()
    {
        $users = User::where('is_active', true)->with('unit')->orderBy('name')->get();

        $filtered = $this->participantSearch
            ? $users->filter(fn ($u) => str_contains(mb_strtolower($u->name.' '.$u->jabatan), mb_strtolower($this->participantSearch)))
            : $users;

        return view('livewire.meeting-form', [
            'users' => $users,
            'groups' => $filtered->groupBy(fn ($u) => $u->unit?->name ?? 'Tanpa unit'),
            'unitIds' => Unit::pluck('id', 'name'),
            'types' => MeetingType::where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
        ])->title($this->meetingId ? 'Ubah Rapat' : 'Rapat Baru');
    }
}
