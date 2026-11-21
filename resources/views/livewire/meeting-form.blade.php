<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <x-btn variant="secondary" size="sm" icon="arrow-left" :href="$meetingId ? route('meetings.show', $meetingId) : route('meetings.index')" wire:navigate class="mb-2">Kembali</x-btn>
            <h1 class="text-2xl font-bold text-slate-900">{{ $meetingId ? 'Ubah Rapat' : 'Rapat Baru' }}</h1>
        </div>
        <x-btn icon="check" wire:click="save" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Simpan Rapat</span>
            <span wire:loading wire:target="save">Menyimpan…</span>
        </x-btn>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
            Periksa kembali isian: {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Data rapat --}}
        <section class="card space-y-4 p-5 lg:col-span-3">
            <h2 class="font-semibold text-slate-800">Data Rapat</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Jenis rapat</label>
                    <select wire:model.live="meeting_type_id" class="input">
                        <option value="">— Rapat umum —</option>
                        @foreach ($types as $t) <option value="{{ $t->id }}">{{ $t->name }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Unit</label>
                    <select wire:model="unit_id" class="input">
                        <option value="">— Tanpa unit —</option>
                        @foreach ($units as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="label">Judul rapat <span class="text-rose-500">*</span></label>
                <input type="text" wire:model="title" class="input" placeholder="mis. Rapat Asatidz SMA Pekan III">
                @error('title') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="label">Tanggal <span class="text-rose-500">*</span></label>
                    <input type="date" wire:model="date" class="input">
                    @error('date') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Jam mulai <span class="text-rose-500">*</span></label>
                    <input type="time" wire:model="start_time" class="input">
                    @error('start_time') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Jam selesai</label>
                    <input type="time" wire:model="end_time" class="input">
                    @error('end_time') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Tempat</label>
                    <input type="text" wire:model="location" class="input" placeholder="mis. Ruang Guru SMA">
                </div>
                <div>
                    <label class="label">Tautan daring (opsional)</label>
                    <input type="url" wire:model="online_link" class="input" placeholder="https://meet.google.com/…">
                    @error('online_link') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="label">Pimpinan rapat <span class="text-rose-500">*</span></label>
                    <select wire:model="chair_id" class="input">
                        <option value="">— Pilih —</option>
                        @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                    </select>
                    @error('chair_id') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Notulis</label>
                    <select wire:model="secretary_id" class="input">
                        <option value="">— Pilih —</option>
                        @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Sifat rapat</label>
                    <select wire:model="confidentiality" class="input">
                        <option value="biasa">Biasa</option>
                        <option value="rahasia">Rahasia (hanya peserta)</option>
                    </select>
                </div>
            </div>

            {{-- Agenda --}}
            <div class="border-t border-slate-100 pt-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold text-slate-800">Agenda Rapat</h2>
                    <x-btn size="sm" icon="plus" wire:click="addAgenda">Tambah agenda</x-btn>
                </div>
                @error('agendas') <p class="error mb-2">{{ $message }}</p> @enderror

                <ol class="space-y-2">
                    @foreach ($agendas as $i => $agenda)
                        <li wire:key="agenda-{{ $i }}-{{ $agenda['id'] ?? 'new' }}" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-start gap-2">
                                <span class="mt-2 w-6 shrink-0 text-center text-sm font-bold text-slate-400">{{ $i + 1 }}</span>
                                <div class="grid flex-1 gap-2 sm:grid-cols-6">
                                    <div class="sm:col-span-3">
                                        <input type="text" wire:model="agendas.{{ $i }}.title" class="input" placeholder="Judul agenda">
                                        @if ($agenda['is_carry_over'])
                                            <p class="mt-1 text-[11px] text-brand-700">↻ Otomatis menampilkan RTL rapat sebelumnya yang belum selesai</p>
                                        @endif
                                        @error("agendas.$i.title") <p class="error">{{ $message }}</p> @enderror
                                    </div>
                                    <select wire:model="agendas.{{ $i }}.presenter_id" class="input sm:col-span-2" title="Penyaji / PIC agenda">
                                        <option value="">Penyaji —</option>
                                        @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                                    </select>
                                    <input type="number" min="1" wire:model="agendas.{{ $i }}.duration_minutes" class="input" placeholder="Menit">
                                </div>
                                <div class="flex shrink-0 flex-col gap-1">
                                    <x-btn variant="secondary" size="sm" icon="chevron-up" wire:click="moveAgenda({{ $i }}, -1)" :disabled="$i === 0" title="Naik" aria-label="Naikkan agenda" />
                                    <x-btn variant="secondary" size="sm" icon="chevron-down" wire:click="moveAgenda({{ $i }}, 1)" :disabled="$i === count($agendas) - 1" title="Turun" aria-label="Turunkan agenda" />
                                </div>
                                <x-btn variant="danger" size="sm" icon="trash" wire:click="removeAgenda({{ $i }})" confirm="Agenda ini beserta pembahasan & keputusan di dalamnya akan terhapus saat rapat disimpan." confirm-title="Hapus agenda?" confirm-button="Hapus" title="Hapus agenda" aria-label="Hapus agenda" />
                            </div>
                        </li>
                    @endforeach
                </ol>
                @php $totalMinutes = collect($agendas)->sum(fn ($a) => (int) ($a['duration_minutes'] ?? 0)); @endphp
                @if ($totalMinutes)
                    <p class="mt-2 text-right text-xs text-slate-500">Total alokasi waktu: {{ $totalMinutes }} menit</p>
                @endif
            </div>
        </section>

        {{-- Peserta --}}
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
    </div>
</div>
