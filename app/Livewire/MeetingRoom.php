<?php

namespace App\Livewire;

use App\Enums\ActionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\MeetingStatus;
use App\Enums\Priority;
use App\Livewire\Concerns\IndonesianValidation;
use App\Models\ActionItem;
use App\Models\Agenda;
use App\Models\Attachment;
use App\Models\Decision;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingSignature;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppNotifier;
use App\Support\AttachmentStorage;
use App\Support\MeetingActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Ruang Rapat: notulen langsung, presensi, keputusan, RTL & evaluasi RTL sebelumnya.
 * Semua perubahan tersimpan tanpa reload halaman.
 */
#[Layout('components.layouts.app')]
class MeetingRoom extends Component
{
    use IndonesianValidation, WithFileUploads;

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

    /** Lampiran agenda yang sedang diunggah (langsung disimpan setelah selesai diunggah). */
    public array $agendaUploads = [];

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
        $absent = $this->pendingParticipantNames();
        $this->meeting->participants()->where('attendance_status', AttendanceStatus::Belum->value)
            ->update(['attendance_status' => AttendanceStatus::Alpa->value]);
        if ($absent) {
            MeetingActivity::log($this->meeting, 'presensi', 'menandai peserta yang belum presensi sebagai alpa (otomatis)', [
                'Jumlah' => count($absent).' orang',
                'Peserta' => $absent,
            ]);
        }
        $this->dispatch('toast', message: 'Rapat selesai. Notulen menunggu pengesahan.');
    }

    public function approve(): void
    {
        abort_unless($this->meeting->canApprove(auth()->user()) && $this->meeting->status === MeetingStatus::Selesai, 403);
        $signature = DB::transaction(function () {
            $this->meeting->update(['status' => MeetingStatus::Disahkan, 'approved_at' => now(), 'approved_by' => auth()->id()]);

            // Tanda tangan digital: kode verifikasi + sidik jari isi notulen saat disahkan
            return MeetingSignature::issue($this->meeting, auth()->user());
        });
        MeetingActivity::log($this->meeting, 'rapat', 'menerbitkan tanda tangan digital', ['Kode verifikasi' => $signature->code]);
        app(WhatsAppNotifier::class)->minutesApproved($this->meeting, $signature);
        $this->dispatch('toast', message: 'Notulen telah disahkan dan dikunci. Kode verifikasi: '.$signature->code);
    }

    public function reopen(): void
    {
        abort_unless(auth()->user()->isAdmin() && $this->meeting->status === MeetingStatus::Disahkan, 403);
        $revoked = DB::transaction(function () {
            $this->meeting->update(['status' => MeetingStatus::Selesai, 'approved_at' => null, 'approved_by' => null]);

            // Kode verifikasi lama tidak berlaku lagi; pengesahan ulang menerbitkan kode baru
            $codes = $this->meeting->signatures()->whereNull('revoked_at')->pluck('code');
            $this->meeting->signatures()->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_by' => auth()->id()]);

            return $codes;
        });
        if ($revoked->isNotEmpty()) {
            MeetingActivity::log($this->meeting, 'rapat', 'mencabut tanda tangan digital', ['Kode verifikasi' => $revoked->all()]);
        }
        $this->dispatch('toast', message: 'Pengesahan dibatalkan. Kode verifikasi lama tidak berlaku lagi.');
    }

    public function cancelMeeting(): void
    {
        $this->ensureCanEdit();
        abort_unless($this->meeting->status === MeetingStatus::Terjadwal, 403);
        $this->meeting->update(['status' => MeetingStatus::Batal]);
        $wa = app(WhatsAppNotifier::class)->meetingCancelled($this->meeting, auth()->id());
        $this->dispatch('toast', message: 'Rapat dibatalkan.'.($wa['antre'] ? " Kabar pembatalan dikirim ke {$wa['antre']} peserta via WhatsApp." : ''));
    }

    /** Kirim undangan WhatsApp ke peserta yang belum menerimanya (aman ditekan ulang). */
    public function sendInvitations(): void
    {
        $this->ensureCanEdit();
        abort_unless(in_array($this->meeting->status, [MeetingStatus::Terjadwal, MeetingStatus::Berlangsung], true), 403);

        $wa = app(WhatsAppNotifier::class)->meetingInvitation($this->meeting, auth()->id());
        if ($wa['antre'] || $wa['dilewati']) {
            MeetingActivity::log($this->meeting, 'rapat', 'mengirim undangan WhatsApp', [
                'Diantrekan' => $wa['antre'].' peserta',
                'Dilewati' => $wa['dilewati'] ? $wa['dilewati'].' peserta (belum ada nomor)' : null,
            ]);
        }

        $this->dispatch('toast', message: match (true) {
            $wa['antre'] > 0 => "Undangan WhatsApp dikirim ke {$wa['antre']} peserta.".($wa['dilewati'] ? " {$wa['dilewati']} peserta belum punya nomor WA." : ''),
            $wa['dilewati'] > 0 => "{$wa['dilewati']} peserta belum punya nomor WA; undangan tidak terkirim.",
            default => 'Semua peserta sudah menerima undangan.',
        });
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
        $agenda = $this->agenda((int) $key);
        $agenda->update(['discussion' => $value]);
        $this->savedAt = now()->format('H:i:s');

        // Tersimpan tiap 1,5 detik saat mengetik → dicatat paling banyak sekali per 10 menit
        MeetingActivity::throttled("pembahasan:{$agenda->id}", 10, fn () => MeetingActivity::log(
            $this->meeting, 'notulen', 'mengubah pembahasan agenda', subject: $agenda, label: $agenda->title,
        ));
    }

    public function updatedNotes($value): void
    {
        $this->ensureCanEdit();
        $this->meeting->update(['notes' => $value]);
        $this->savedAt = now()->format('H:i:s');

        MeetingActivity::throttled("catatan:{$this->meeting->id}", 10, fn () => MeetingActivity::log(
            $this->meeting, 'notulen', 'mengubah catatan umum rapat',
        ));
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
        // Hapus satu per satu agar setiap RTL yang ikut terhapus tercatat di log
        $decision->actionItems()->get()->each->delete();
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
            $item = $this->meeting->actionItems()->whereKey($this->editingRtlId)->firstOrFail();
            $picChanged = $item->pic_id !== $values['pic_id'];
            $item->update($values);
        } else {
            $item = $this->meeting->actionItems()->create($values + [
                'decision_id' => $this->rtlDecisionId,
                'status' => ActionStatus::Belum,
                'progress' => 0,
            ]);
            $picChanged = true;
        }

        // Beri tahu penanggung jawab (baru) lewat WhatsApp, kecuali bila ia sendiri yang mencatat
        if ($picChanged) {
            app(WhatsAppNotifier::class)->actionAssigned($item, auth()->id());
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

    // ---------------------------------------------------------------- Lampiran agenda

    /** Dipanggil otomatis setelah file selesai diunggah (pilih file atau seret ke area lampiran). */
    public function updatedAgendaUploads(): void
    {
        $this->ensureCanEdit();

        try {
            $this->validateAgendaUploads();
        } catch (ValidationException $e) {
            // Buang berkas yang ditolak agar unggahan berikutnya tidak ikut gagal
            $this->reset('agendaUploads');

            throw $e;
        }

        $agenda = $this->agenda($this->activeAgendaId);
        foreach ($this->agendaUploads as $file) {
            AttachmentStorage::store($file, $agenda, $this->meeting->id, auth()->user());
        }

        $count = count($this->agendaUploads);
        $this->reset('agendaUploads');
        $this->dispatch('toast', message: $count.' lampiran diunggah.');
    }

    protected function validateAgendaUploads(): void
    {
        $this->validate([
            'agendaUploads' => ['array', 'max:'.AttachmentStorage::maxFiles()],
            'agendaUploads.*' => AttachmentStorage::rules(),
        ], [
            'agendaUploads.max' => 'Maksimal '.AttachmentStorage::maxFiles().' berkas sekali unggah.',
            'agendaUploads.*.max' => 'Berkas :attribute melebihi '.round(config('rapat.attachments.max_kb') / 1024).' MB.',
            'agendaUploads.*.mimes' => 'Berkas :attribute tidak didukung. Gunakan PDF, Word, Excel, PowerPoint, JPG, PNG, atau WEBP.',
            'agendaUploads.*.uploaded' => 'Berkas :attribute gagal diunggah (mungkin terlalu besar).',
        ], $this->uploadAttributeNames('agendaUploads'));
    }

    public function deleteAttachment(int $id): void
    {
        $attachment = Attachment::where('meeting_id', $this->meeting->id)->whereKey($id)->firstOrFail();
        abort_unless($attachment->canDelete(auth()->user()), 403);
        $attachment->delete();
        $this->dispatch('toast', message: 'Lampiran dihapus.');
    }

    /** Nama berkas asli sebagai :attribute agar pesan galat menyebut berkas yang bermasalah. */
    protected function uploadAttributeNames(string $property): array
    {
        return collect($this->{$property})
            ->mapWithKeys(fn ($file, $i) => ["{$property}.{$i}" => '“'.(method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : 'berkas').'”'])
            ->all();
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
        $present = $this->pendingParticipantNames();
        $this->meeting->participants()->where('attendance_status', AttendanceStatus::Belum->value)
            ->update(['attendance_status' => AttendanceStatus::Hadir->value, 'checked_in_at' => now()]);
        if ($present) {
            MeetingActivity::log($this->meeting, 'presensi', 'menandai semua peserta yang belum presensi hadir', [
                'Jumlah' => count($present).' orang',
                'Peserta' => $present,
            ]);
        }
        $this->dispatch('toast', message: 'Semua peserta yang belum presensi ditandai hadir.');
    }

    /** Nama peserta yang belum presensi (untuk ringkasan log operasi massal). */
    protected function pendingParticipantNames(): array
    {
        return $this->meeting->participants()
            ->where('attendance_status', AttendanceStatus::Belum->value)
            ->with('user:id,name')
            ->get()
            ->map(fn (MeetingParticipant $p) => $p->displayName())
            ->all();
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
        $this->meeting->refresh()->load(['type', 'unit', 'chair', 'secretary', 'approver', 'activeSignature', 'schedule']);

        $agendas = $this->meeting->agendas()
            ->with(['presenter', 'decisions.actionItems.pic', 'attachments.uploader'])
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
            'allActions' => $this->meeting->actionItems()->with(['pic', 'decision', 'evidenceAttachments'])->orderBy('due_date')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'jabatan']),
            'canEdit' => $this->canEdit(),
            'canManage' => $this->meeting->canManage($user),
            'waStats' => $this->meeting->canManage($user)
                ? WhatsappMessage::where('meeting_id', $this->meeting->id)->where('type', 'undangan')
                    ->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status')
                : collect(),
            'canApprove' => $this->meeting->canApprove($user),
            'isAdmin' => $user->isAdmin(),
            'checkinUrl' => route('checkin', $this->meeting->checkin_token),
        ])->title($this->meeting->title);
    }
}
