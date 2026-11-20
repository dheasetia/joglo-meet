<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ $meetingId ? route('meetings.show', $meetingId) : route('meetings.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-emerald-700">← Kembali</a>
            <h1 class="text-2xl font-bold text-slate-900">{{ $meetingId ? 'Ubah Rapat' : 'Rapat Baru' }}</h1>
        </div>
        <button wire:click="save" wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="save">Simpan Rapat</span>
            <span wire:loading wire:target="save">Menyimpan…</span>
        </button>
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
                    <button type="button" wire:click="addAgenda" class="btn-secondary btn-sm">+ Tambah agenda</button>
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
                                            <p class="mt-1 text-[11px] text-emerald-700">↻ Otomatis menampilkan RTL rapat sebelumnya yang belum selesai</p>
                                        @endif
                                        @error("agendas.$i.title") <p class="error">{{ $message }}</p> @enderror
                                    </div>
                                    <select wire:model="agendas.{{ $i }}.presenter_id" class="input sm:col-span-2" title="Penyaji / PIC agenda">
                                        <option value="">Penyaji —</option>
                                        @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                                    </select>
                                    <input type="number" min="1" wire:model="agendas.{{ $i }}.duration_minutes" class="input" placeholder="Menit">
                                </div>
                                <div class="flex shrink-0 flex-col">
                                    <button type="button" wire:click="moveAgenda({{ $i }}, -1)" @disabled($i === 0) class="btn-ghost btn-sm" title="Naik">▲</button>
                                    <button type="button" wire:click="moveAgenda({{ $i }}, 1)" @disabled($i === count($agendas) - 1) class="btn-ghost btn-sm" title="Turun">▼</button>
                                </div>
                                <button type="button" wire:click="removeAgenda({{ $i }})" wire:confirm="Hapus agenda ini? Pembahasan & keputusan di dalamnya ikut terhapus saat disimpan." class="btn-ghost btn-sm mt-1 text-rose-600" title="Hapus">✕</button>
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
                <span class="badge bg-emerald-100 text-emerald-700">{{ count($participantIds) }} dipilih</span>
            </div>
            <input type="search" wire:model.live.debounce.300ms="participantSearch" class="input mb-3" placeholder="Cari nama atau jabatan…">
            <p class="mb-3 text-xs text-slate-500">Pimpinan rapat dan notulis otomatis menjadi peserta.</p>

            <div class="max-h-[34rem] flex-1 space-y-4 overflow-y-auto pr-1">
                @foreach ($groups as $unitName => $members)
                    <div wire:key="unit-{{ $unitName }}">
                        <div class="mb-1 flex items-center justify-between">
                            <p class="text-xs font-bold tracking-wide text-slate-500 uppercase">{{ $unitName }}</p>
                            <button type="button" wire:click="toggleUnit({{ $unitIds[$unitName] ?? 'null' }})" class="text-xs font-semibold text-emerald-700 hover:underline">Pilih/batal semua</button>
                        </div>
                        <div class="space-y-1">
                            @foreach ($members as $u)
                                <label wire:key="u-{{ $u->id }}" class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50">
                                    <input type="checkbox" value="{{ $u->id }}" wire:model.live="participantIds" class="size-4 rounded border-slate-300 text-emerald-600">
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
