{{-- Form tambah/ubah RTL di Ruang Rapat. Variabel: $users --}}
<div wire:key="rtl-form-{{ $editingRtlId ?? 'new' }}" class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-3">
    <p class="mb-2 text-xs font-bold tracking-wide text-emerald-800 uppercase">{{ $editingRtlId ? 'Ubah RTL' : 'RTL baru' }}</p>
    <div class="grid gap-2 sm:grid-cols-12">
        <div class="sm:col-span-12">
            <input type="text" wire:model="rtl.title" class="input" placeholder="Uraian tugas, mis. Menyusun jadwal piket musyrif">
            @error('rtl.title') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-5">
            <select wire:model="rtl.pic_id" class="input">
                <option value="">Penanggung jawab…</option>
                @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}{{ $u->jabatan ? ' — '.$u->jabatan : '' }}</option> @endforeach
            </select>
            @error('rtl.pic_id') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-4">
            <input type="date" wire:model="rtl.due_date" class="input" title="Deadline">
            @error('rtl.due_date') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-3">
            <select wire:model="rtl.priority" class="input" title="Prioritas">
                @foreach (\App\Enums\Priority::cases() as $p) <option value="{{ $p->value }}">Prioritas {{ $p->label() }}</option> @endforeach
            </select>
        </div>
        <div class="sm:col-span-12">
            <input type="text" wire:model="rtl.success_indicator" class="input" placeholder="Indikator selesai (opsional), mis. Jadwal ditempel di asrama">
        </div>
    </div>
    <div class="mt-2 flex justify-end gap-2">
        <button wire:click="cancelEditing" class="btn-ghost btn-sm">Batal</button>
        <button wire:click="saveRtl" class="btn-primary btn-sm">Simpan RTL</button>
    </div>
</div>
