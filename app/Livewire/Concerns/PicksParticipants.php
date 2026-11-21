<?php

namespace App\Livewire\Concerns;

use App\Models\Unit;
use App\Models\User;

/**
 * Pemilih peserta per unit (dipakai Form Rapat & Form Jadwal Rutin),
 * bersama partial livewire.partials.participant-picker.
 */
trait PicksParticipants
{
    /** @var array<int, string> */
    public array $participantIds = [];

    public string $participantSearch = '';

    /** Pilih semua anggota aktif satu unit; bila semua sudah terpilih, batalkan. */
    public function toggleUnit(?int $unitId): void
    {
        $ids = User::where('is_active', true)->where('unit_id', $unitId)->pluck('id')->map(fn ($id) => (string) $id);
        $current = collect($this->participantIds);

        $this->participantIds = $ids->diff($current)->isEmpty()
            ? $current->diff($ids)->values()->all()
            : $current->merge($ids)->unique()->values()->all();
    }

    /** Data untuk partial participant-picker: pengguna aktif dikelompokkan per unit. */
    protected function participantPickerData(): array
    {
        $users = User::where('is_active', true)->with('unit')->orderBy('name')->get();

        $filtered = $this->participantSearch
            ? $users->filter(fn ($u) => str_contains(mb_strtolower($u->name.' '.$u->jabatan), mb_strtolower($this->participantSearch)))
            : $users;

        return [
            'users' => $users,
            'groups' => $filtered->groupBy(fn ($u) => $u->unit?->name ?? 'Tanpa unit'),
            'unitIds' => Unit::pluck('id', 'name'),
        ];
    }
}
