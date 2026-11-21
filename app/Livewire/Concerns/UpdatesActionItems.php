<?php

namespace App\Livewire\Concerns;

use App\Enums\ActionStatus;
use App\Models\ActionItem;
use App\Support\AttachmentStorage;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;

/**
 * Modal "Perbarui progres RTL" yang dipakai di Papan RTL dan Tugas Saya.
 */
trait UpdatesActionItems
{
    use WithFileUploads;

    public ?int $updatingId = null;

    /** Bukti progres (foto/dokumen), opsional. */
    public array $evidenceUploads = [];

    public array $progressForm = ['progress' => 0, 'status' => 'proses', 'note' => ''];

    public function openUpdate(int $id): void
    {
        $item = ActionItem::findOrFail($id);
        abort_unless($item->canUpdate(auth()->user()), 403);

        $this->resetValidation();
        $this->reset('evidenceUploads');
        $this->updatingId = $item->id;
        $this->progressForm = [
            'progress' => $item->progress,
            'status' => $item->status->value,
            'note' => '',
        ];
    }

    public function closeUpdate(): void
    {
        $this->updatingId = null;
        $this->reset('evidenceUploads');
    }

    public function removeEvidence(int $index): void
    {
        unset($this->evidenceUploads[$index]);
        $this->evidenceUploads = array_values($this->evidenceUploads);
    }

    public function saveUpdate(): void
    {
        $this->validate([
            'progressForm.progress' => ['required', 'integer', 'min:0', 'max:100'],
            'progressForm.status' => ['required', Rule::enum(ActionStatus::class)],
            'progressForm.note' => ['nullable', 'string', 'max:2000'],
            'evidenceUploads' => ['array', 'max:'.AttachmentStorage::maxFiles()],
            'evidenceUploads.*' => AttachmentStorage::rules(),
        ], [
            'evidenceUploads.max' => 'Maksimal '.AttachmentStorage::maxFiles().' berkas bukti.',
            'evidenceUploads.*.max' => 'Berkas :attribute melebihi '.round(config('rapat.attachments.max_kb') / 1024).' MB.',
            'evidenceUploads.*.mimes' => 'Berkas :attribute tidak didukung. Gunakan PDF, Word, Excel, PowerPoint, JPG, PNG, atau WEBP.',
            'evidenceUploads.*.uploaded' => 'Berkas :attribute gagal diunggah (mungkin terlalu besar).',
        ], [
            'progressForm.progress' => 'Progres',
            'progressForm.status' => 'Status',
            'progressForm.note' => 'Catatan',
            ...collect($this->evidenceUploads)->mapWithKeys(fn ($file, $i) => ["evidenceUploads.{$i}" => '“'.$file->getClientOriginalName().'”'])->all(),
        ]);

        $item = ActionItem::findOrFail($this->updatingId);
        abort_unless($item->canUpdate(auth()->user()), 403);

        $update = $item->recordProgress(
            auth()->user(),
            (int) $this->progressForm['progress'],
            ActionStatus::from($this->progressForm['status']),
            $this->progressForm['note'],
        );

        foreach ($this->evidenceUploads as $file) {
            AttachmentStorage::store($file, $update, $item->meeting_id, auth()->user());
        }

        $this->updatingId = null;
        $this->reset('evidenceUploads');
        $this->dispatch('toast', message: 'Progres RTL tersimpan.');
    }

    protected function updatingItem(): ?ActionItem
    {
        return $this->updatingId
            ? ActionItem::with(['pic', 'meeting', 'updates.user', 'updates.attachments'])->find($this->updatingId)
            : null;
    }
}
