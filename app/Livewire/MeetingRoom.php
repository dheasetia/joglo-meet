<?php

namespace App\Livewire;

use App\Enums\ActionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\MeetingStatus;
use App\Enums\Priority;
use App\Livewire\Concerns\IndonesianValidation;
use App\Models\ActionItem;
use App\Models\Agenda;
use App\Models\Decision;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ruang Rapat: notulen langsung, presensi, keputusan, RTL & evaluasi RTL sebelumnya.
 * Semua perubahan tersimpan tanpa reload halaman.
 */
#[Layout('components.layouts.app')]
class MeetingRoom extends Component
{
    use IndonesianValidation;

    public Meeting $meeting;

    public ?int $activeAgendaId = null;

    /** Teks pembahasan per agenda: [agendaId => teks] */
    public array $discussions = [];

    public string $notes = '';

    // Keputusan
    public string $newDecision = '';
    public ?int $editingDecisionId = null;
    public string $editingDecisionText = '';

    // Form RTL
    public ?int $rtlDecisionId = null;
    public ?int $editingRtlId = null;
    public array $rtl = [];

    // Evaluasi RTL rapat sebelumnya: [actionId => [progress, status, note]]
    public array $carry = [];

    // Tamu
    public string $guestName = '';
    public string $guestInfo = '';

    public ?string $savedAt = null;

    public function mount(Meeting $meeting): void
    {
        abort_unless($meeting->canView(auth()->user()), 403);

        $this->meeting = $meeting;
        $agendas = $meeting->agendas()->get();
        $this->discussions = $agendas->mapWithKeys(fn ($a) => [$a->id => (string) $a->discussion])->all();
        $this->activeAgendaId = $agendas->first(fn ($a) => ! $a->is_carry_over && $a->sort_order > 1)?->id ?? $agendas->first()?->id;
        $this->notes = (string) $meeting->notes;
        $this->resetRtlForm();
    }

    // ---------------------------------------------------------------- Hak akses

    protected function canEdit(): bool
    {
        return $this->meeting->canManage(auth()->user()) && ! $this->meeting->isLocked();
    }

    protected function ensureCanEdit(): void
    {
        abort_unless($this->canEdit(), 403);
    }

    protected function agenda(int $id): Agenda
    {
        return $this->meeting->agendas()->whereKey($id)->firstOrFail();
    }

    // ---------------------------------------------------------------- Status rapat

    public function startMeeting(): void
    {
        $this->ensureCanEdit();
        $this->meeting->update(['status' => MeetingStatus::Berlangsung, 'started_at' => $this->meeting->started_at ?? now()]);
        $this->dispatch('toast', message: 'Bismillah, rapat dimulai.');
    }

    public function endMeeting(): void
    {
        $this->ensureCanEdit();
        $this->meeting->update(['status' => MeetingStatus::Selesai, 'ended_at' => now()]);
        // Peserta yang tidak presensi dianggap alpa
        $this->meeting->participants()->where('attendance_status', AttendanceStatus::Belum->value)
            ->update(['attendance_status' => AttendanceStatus::Alpa->value]);
        $this->dispatch('toast', message: 'Rapat selesai. Notulen menunggu pengesahan.');
    }

    public function approve(): void
    {
        abort_unless($this->meeting->canApprove(auth()->user()) && $this->meeting->status === MeetingStatus::Selesai, 403);
        $this->meeting->update(['status' => MeetingStatus::Disahkan, 'approved_at' => now(), 'approved_by' => auth()->id()]);
        $this->dispatch('toast', message: 'Notulen telah disahkan dan dikunci.');
    }

    public function reopen(): void
    {
        abort_unless(auth()->user()->isAdmin() && $this->meeting->status === MeetingStatus::Disahkan, 403);
        $this->meeting->update(['status' => MeetingStatus::Selesai, 'approved_at' => null, 'approved_by' => null]);
        $this->dispatch('toast', message: 'Pengesahan dibatalkan. Notulen dapat diubah kembali.');
    }

    public function cancelMeeting(): void
    {
        $this->ensureCanEdit();
        abort_unless($this->meeting->status === MeetingStatus::Terjadwal, 403);
        $this->meeting->update(['status' => MeetingStatus::Batal]);
        $this->dispatch('toast', message: 'Rapat dibatalkan.');
    }

    // ---------------------------------------------------------------- Notulen

    public function selectAgenda(int $id): void
    {
        $this->activeAgendaId = $this->agenda($id)->id;
        $this->cancelEditing();
    }

    /** Autosave pembahasan (dipanggil otomatis oleh wire:model.live.debounce). */
    public function updatedDiscussions($value, $key): void
    {
        $this->ensureCanEdit();
        $this->agenda((int) $key)->update(['discussion' => $value]);
        $this->savedAt = now()->format('H:i:s');
    }

    public function updatedNotes($value): void
    {
        $this->ensureCanEdit();
        $this->meeting->update(['notes' => $value]);
        $this->savedAt = now()->format('H:i:s');
    }

    // ---------------------------------------------------------------- Keputusan

    public function addDecision(): void
    {
        $this->ensureCanEdit();
        $this->validate(['newDecision' => ['required', 'string', 'max:2000']], [], ['newDecision' => 'Keputusan']);

        $agenda = $this->agenda($this->activeAgendaId);
        $decision = $this->meeting->decisions()->create([
            'agenda_id' => $agenda->id,
            'content' => trim($this->newDecision),
            'sort_order' => (int) $this->meeting->decisions()->max('sort_order') + 1,
        ]);

        $this->newDecision = '';
        $this->openRtlForm($decision->id); // langsung tawarkan RTL untuk keputusan ini
        $this->dispatch('toast', message: 'Keputusan dicatat. Tambahkan RTL-nya.');
    }

    public function editDecision(int $id): void
    {
        $this->ensureCanEdit();
        $decision = $this->meeting->decisions()->whereKey($id)->firstOrFail();
        $this->editingDecisionId = $decision->id;
        $this->editingDecisionText = $decision->content;
    }

    public function updateDecision(): void
    {
        $this->ensureCanEdit();
        $this->validate(['editingDecisionText' => ['required', 'string', 'max:2000']], [], ['editingDecisionText' => 'Keputusan']);
        $this->meeting->decisions()->whereKey($this->editingDecisionId)->firstOrFail()
            ->update(['content' => trim($this->editingDecisionText)]);
        $this->cancelEditing();
    }

    public function deleteDecision(int $id): void
    {
        $this->ensureCanEdit();
        $decision = $this->meeting->decisions()->whereKey($id)->firstOrFail();
        $decision->actionItems()->delete();
        $decision->delete();
        $this->dispatch('toast', message: 'Keputusan beserta RTL-nya dihapus.');
    }

    public function cancelEditing(): void
    {
        $this->editingDecisionId = null;
        $this->editingDecisionText = '';
        $this->rtlDecisionId = null;
        $this->editingRtlId = null;
        $this->resetValidation();
    }

    // ---------------------------------------------------------------- RTL

    protected function resetRtlForm(): void
    {
        $this->rtl = [
            'title' => '',
            'pic_id' => '',
            'due_date' => today()->addWeek()->format('Y-m-d'),
            'priority' => Priority::Sedang->value,
            'success_indicator' => '',
        ];
    }

    public function openRtlForm(int $decisionId): void
    {
        $this->ensureCanEdit();
        $this->cancelEditing();
        $this->resetRtlForm();
        $this->rtlDecisionId = $this->meeting->decisions()->whereKey($decisionId)->firstOrFail()->id;
    }

    public function editRtl(int $id): void
    {
        $this->ensureCanEdit();
        $item = $this->meeting->actionItems()->whereKey($id)->firstOrFail();
        $this->cancelEditing();
        $this->editingRtlId = $item->id;
        $this->rtlDecisionId = $item->decision_id;
        $this->rtl = [
            'title' => $item->title,
            'pic_id' => (string) $item->pic_id,
            'due_date' => $item->due_date->format('Y-m-d'),
            'priority' => $item->priority->value,
            'success_indicator' => (string) $item->success_indicator,
        ];
    }

    public function saveRtl(): void
    {
        $this->ensureCanEdit();
        $data = $this->validate([
            'rtl.title' => ['required', 'string', 'max:255'],
            'rtl.pic_id' => ['required', 'exists:users,id'],
            'rtl.due_date' => ['required', 'date'],
            'rtl.priority' => ['required', Rule::enum(Priority::class)],
            'rtl.success_indicator' => ['nullable', 'string', 'max:255'],
        ], [], [
            'rtl.title' => 'Uraian tugas',
            'rtl.pic_id' => 'Penanggung jawab',
            'rtl.due_date' => 'Deadline',
            'rtl.priority' => 'Prioritas',
            'rtl.success_indicator' => 'Indikator selesai',
        ])['rtl'];

        $values = [
            'title' => $data['title'],
            'pic_id' => (int) $data['pic_id'],
            'due_date' => $data['due_date'],
            'priority' => $data['priority'],
            'success_indicator' => $data['success_indicator'] ?: null,
        ];

        if ($this->editingRtlId) {
            $this->meeting->actionItems()->whereKey($this->editingRtlId)->firstOrFail()->update($values);
        } else {
            $this->meeting->actionItems()->create($values + [
                'decision_id' => $this->rtlDecisionId,
                'status' => ActionStatus::Belum,
                'progress' => 0,
            ]);
        }

        $this->cancelEditing();
        $this->dispatch('toast', message: 'RTL tersimpan.');
    }

    public function deleteRtl(int $id): void
    {
        $this->ensureCanEdit();
        $this->meeting->actionItems()->whereKey($id)->firstOrFail()->delete();
        $this->dispatch('toast', message: 'RTL dihapus.');
    }

    /** Evaluasi RTL rapat sebelumnya: catat progres terbaru langsung dari forum rapat. */
    public function saveCarry(int $actionId): void
    {
        $this->ensureCanEdit();
        $item = $this->meeting->carryOverActionItems()->firstWhere('id', $actionId);
        abort_unless($item, 404);

        $row = $this->carry[$actionId] ?? [];
        $status = ActionStatus::tryFrom($row['status'] ?? '') ?? $item->status;
        $progress = (int) ($row['progress'] ?? $item->progress);

        ActionItem::findOrFail($actionId)
            ->recordProgress(auth()->user(), $progress, $status, $row['note'] ?? null, $this->meeting->id);

        unset($this->carry[$actionId]);
        $this->dispatch('toast', message: 'Evaluasi RTL dicatat.');
    }

    // ---------------------------------------------------------------- Presensi

    public function setAttendance(int $participantId, string $status): void
    {
        $this->ensureCanEdit();
        $status = AttendanceStatus::from($status);
        $participant = $this->meeting->participants()->whereKey($participantId)->firstOrFail();

        $participant->update([
            'attendance_status' => $status,
            'checked_in_at' => $status->isPresent() ? ($participant->checked_in_at ?? now()) : null,
        ]);
    }

    public function setAllPresent(): void
    {
        $this->ensureCanEdit();
        $this->meeting->participants()->where('attendance_status', AttendanceStatus::Belum->value)
            ->update(['attendance_status' => AttendanceStatus::Hadir->value, 'checked_in_at' => now()]);
        $this->dispatch('toast', message: 'Semua peserta yang belum presensi ditandai hadir.');
    }

    public function updateParticipantNote(int $participantId, string $note): void
    {
        $this->ensureCanEdit();
        $this->meeting->participants()->whereKey($participantId)->firstOrFail()
            ->update(['note' => mb_substr(trim($note), 0, 255) ?: null]);
    }

    public function addGuest(): void
    {
        $this->ensureCanEdit();
        $this->validate(['guestName' => ['required', 'string', 'max:255'], 'guestInfo' => ['nullable', 'string', 'max:255']], [], ['guestName' => 'Nama tamu']);

        $this->meeting->participants()->create([
            'guest_name' => trim($this->guestName),
            'guest_info' => trim($this->guestInfo) ?: null,
            'attendance_status' => AttendanceStatus::Hadir,
            'checked_in_at' => now(),
        ]);
        $this->reset(['guestName', 'guestInfo']);
    }

    public function removeParticipant(int $participantId): void
    {
        $this->ensureCanEdit();
        $participant = $this->meeting->participants()->whereKey($participantId)->firstOrFail();
        abort_if(in_array($participant->user_id, [$this->meeting->chair_id, $this->meeting->secretary_id], true), 403);
        $participant->delete();
    }

    // ---------------------------------------------------------------- Render

    public function render()
    {
        $this->meeting->refresh()->load(['type', 'unit', 'chair', 'secretary', 'approver']);

        $agendas = $this->meeting->agendas()
            ->with(['presenter', 'decisions.actionItems.pic'])
            ->get();

        $participants = MeetingParticipant::where('meeting_id', $this->meeting->id)
            ->with('user.unit')
            ->get()
            ->sortBy(fn ($p) => [$p->user_id ? 0 : 1, $p->displayName()])
            ->values();

        $carryOver = $agendas->contains('is_carry_over', true) ? $this->meeting->carryOverActionItems() : collect();
        foreach ($carryOver as $item) {
            $this->carry[$item->id] ??= ['progress' => $item->progress, 'status' => $item->status->value, 'note' => ''];
        }

        $user = auth()->user();

        return view('livewire.meeting-room', [
            'agendas' => $agendas,
            'activeAgenda' => $agendas->firstWhere('id', $this->activeAgendaId),
            'participants' => $participants,
            'attendance' => $this->meeting->attendanceSummary(),
            'carryOver' => $carryOver,
            'allActions' => $this->meeting->actionItems()->with(['pic', 'decision'])->orderBy('due_date')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'jabatan']),
            'canEdit' => $this->canEdit(),
            'canApprove' => $this->meeting->canApprove($user),
            'isAdmin' => $user->isAdmin(),
            'checkinUrl' => route('checkin', $this->meeting->checkin_token),
        ])->title($this->meeting->title);
    }
}
