<?php

namespace App\Livewire\Concerns;

use App\Enums\ActionStatus;
use App\Models\ActionItem;
use Illuminate\Validation\Rule;

/**
 * Modal "Perbarui progres RTL" yang dipakai di Papan RTL dan Tugas Saya.
 */
trait UpdatesActionItems
{
    public ?int $updatingId = null;

    public array $progressForm = ['progress' => 0, 'status' => 'proses', 'note' => ''];

    public function openUpdate(int $id): void
    {
        $item = ActionItem::findOrFail($id);
        abort_unless($item->canUpdate(auth()->user()), 403);

        $this->resetValidation();
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
    }

    public function saveUpdate(): void
    {
        $this->validate([
            'progressForm.progress' => ['required', 'integer', 'min:0', 'max:100'],
            'progressForm.status' => ['required', Rule::enum(ActionStatus::class)],
            'progressForm.note' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'progressForm.progress' => 'Progres',
            'progressForm.status' => 'Status',
            'progressForm.note' => 'Catatan',
        ]);

        $item = ActionItem::findOrFail($this->updatingId);
        abort_unless($item->canUpdate(auth()->user()), 403);

        $item->recordProgress(
            auth()->user(),
            (int) $this->progressForm['progress'],
            ActionStatus::from($this->progressForm['status']),
            $this->progressForm['note'],
        );

        $this->updatingId = null;
        $this->dispatch('toast', message: 'Progres RTL tersimpan.');
    }

    protected function updatingItem(): ?ActionItem
    {
        return $this->updatingId
            ? ActionItem::with(['pic', 'meeting', 'updates.user'])->find($this->updatingId)
            : null;
    }
}
