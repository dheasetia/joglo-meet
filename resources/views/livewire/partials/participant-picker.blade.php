{{-- Pemilih peserta per unit. Butuh trait PicksParticipants: $groups, $unitIds, $participantIds --}}
<section class="card flex flex-col p-5 lg:col-span-2">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="font-semibold text-slate-800">Peserta Undangan</h2>
        <span class="badge bg-brand-100 text-brand-700">{{ count($participantIds) }} dipilih</span>
    </div>
    <input type="search" wire:model.live.debounce.300ms="participantSearch" class="input mb-3" placeholder="Cari nama atau jabatan…">
    <p class="mb-3 text-xs text-slate-500">Pimpinan rapat dan notulis otomatis menjadi peserta.</p>

    <div class="max-h-[34rem] flex-1 space-y-4 overflow-y-auto pr-1">
        @foreach ($groups as $unitName => $members)
            <div wire:key="unit-{{ $unitName }}">
                <div class="mb-1 flex items-center justify-between">
                    <p class="text-xs font-bold tracking-wide text-slate-500 uppercase">{{ $unitName }}</p>
                    <x-btn variant="secondary" size="sm" icon="check-circle" wire:click="toggleUnit({{ $unitIds[$unitName] ?? 'null' }})">Pilih/batal semua</x-btn>
                </div>
                <div class="space-y-1">
                    @foreach ($members as $u)
                        <label wire:key="u-{{ $u->id }}" class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50">
                            <input type="checkbox" value="{{ $u->id }}" wire:model.live="participantIds" class="size-4 rounded border-slate-300 text-brand-600">
                            <span class="min-w-0">
                                <span class="block truncate text-sm text-slate-800">{{ $u->name }}</span>
                                @if ($u->jabatan) <span class="block truncate text-xs text-slate-400">{{ $u->jabatan }}</span> @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>
