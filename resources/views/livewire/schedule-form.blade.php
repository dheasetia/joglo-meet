<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <x-btn variant="secondary" size="sm" icon="arrow-left" :href="route('schedules.index')" wire:navigate class="mb-2">Jadwal rutin</x-btn>
            <h1 class="text-2xl font-bold text-slate-900">{{ $scheduleId ? 'Ubah Jadwal Rutin' : 'Jadwal Rutin Baru' }}</h1>
            <p class="text-sm text-slate-500">Rapat dibuat otomatis beberapa hari sebelumnya, lengkap dengan agenda dan peserta.</p>
        </div>
        <x-btn icon="check" wire:click="save" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Simpan Jadwal</span>
            <span wire:loading wire:target="save">Menyimpan…</span>
        </x-btn>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">Periksa kembali isian: {{ $errors->first() }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            {{-- Data rapat --}}
            <section class="card space-y-4 p-5">
                <h2 class="font-semibold text-slate-800">Data Rapat</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Jenis rapat</label>
                        <select wire:model.live="meeting_type_id" class="input">
                            <option value="">— Rapat umum —</option>
                            @foreach ($types as $t) <option value="{{ $t->id }}">{{ $t->name }}</option> @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Agenda inti diambil dari template jenis rapat.</p>
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
                    <input type="text" wire:model="title" class="input" placeholder="mis. Rapat Asatidz SMA Mingguan">
                    <p class="mt-1 text-xs text-slate-400">Tanggal ditambahkan otomatis, mis. “… · Sabtu, 28 Nov 2026”.</p>
                    @error('title') <p class="error">{{ $message }}</p> @enderror
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
            </section>

            {{-- Pola jadwal --}}
            <section class="card space-y-4 p-5">
                <h2 class="font-semibold text-slate-800">Pola Jadwal</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label">Berulang</label>
                        <select wire:model.live="frequency" class="input">
                            @foreach ($frequencies as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                        </select>
                    </div>
                    @if ($frequency === 'bulanan')
                        <div>
                            <label class="label">Pekan</label>
                            <select wire:model.live="week_of_month" class="input">
                                @foreach ($weeks as $value => $label) <option value="{{ $value }}">{{ ucfirst($label) }}</option> @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="label">Hari</label>
                        <select wire:model.live="day_of_week" class="input">
                            @foreach ($days as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Jam mulai <span class="text-rose-500">*</span></label>
                        <input type="time" wire:model.live.debounce.500ms="start_time" class="input">
                        @error('start_time') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Jam selesai</label>
                        <input type="time" wire:model="end_time" class="input">
                        @error('end_time') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label">Berlaku mulai <span class="text-rose-500">*</span></label>
                        <input type="date" wire:model.live="starts_on" class="input">
                        @error('starts_on') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Berlaku sampai</label>
                        <input type="date" wire:model.live="ends_on" class="input">
                        <p class="mt-1 text-xs text-slate-400">Kosongkan bila tanpa batas.</p>
                        @error('ends_on') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Dibuat … hari sebelumnya</label>
                        <input type="number" min="1" max="30" wire:model="lead_days" class="input">
                        @error('lead_days') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Pratinjau tanggal --}}
                <div class="rounded-xl bg-emerald-50/70 p-4">
                    <p class="mb-2 text-xs font-bold tracking-wide text-emerald-800 uppercase">5 rapat berikutnya</p>
                    @if ($preview)
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($preview as $date)
                                <li class="rounded-lg bg-white px-2.5 py-1 text-sm font-medium text-slate-700 shadow-sm">
                                    {{ $days[$date->dayOfWeekIso] }}, {{ $date->translatedFormat('d M Y') }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-slate-500">Tidak ada tanggal rapat dalam masa berlaku jadwal.</p>
                    @endif
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="is_active" class="size-4 rounded border-slate-300 text-emerald-600">
                    Jadwal aktif (rapat dibuat otomatis)
                </label>
            </section>
        </div>

        @include('livewire.partials.participant-picker')
    </div>
</div>
