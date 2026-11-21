<div x-data="{ tab: 'notulen' }" class="space-y-5">
    {{-- ============ Header ============ --}}
    <div class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <x-btn variant="secondary" size="sm" icon="arrow-left" :href="route('meetings.index')" wire:navigate class="mb-2">Daftar rapat</x-btn>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">{{ $meeting->title }}</h1>
                    <span class="badge {{ $meeting->status->badge() }}">{{ $meeting->status->label() }}</span>
                    @if ($meeting->isConfidential()) <span class="badge bg-slate-800 text-white">Rahasia</span> @endif
                </div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                    <span>📅 {{ $meeting->date->translatedFormat('l, d F Y') }}</span>
                    <span>🕐 {{ $meeting->timeRange() }}</span>
                    @if ($meeting->location) <span>📍 {{ $meeting->location }}</span> @endif
                    @if ($meeting->online_link) <a href="{{ $meeting->online_link }}" target="_blank" rel="noopener" class="text-brand-700 hover:underline">🔗 Tautan daring</a> @endif
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    Pimpinan: <b class="text-slate-700">{{ $meeting->chair->name }}</b>
                    @if ($meeting->secretary) · Notulis: <b class="text-slate-700">{{ $meeting->secretary->name }}</b> @endif
                    @if ($meeting->type) · {{ $meeting->type->name }} @endif
                </p>
                @if ($meeting->status === \App\Enums\MeetingStatus::Disahkan)
                    <p class="mt-1 text-xs font-medium text-emerald-700">✓ Disahkan oleh {{ $meeting->approver?->name }} pada {{ $meeting->approved_at->translatedFormat('d M Y H:i') }}</p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($savedAt && $canEdit)
                    <span class="text-xs text-slate-400" wire:key="saved-{{ $savedAt }}">Tersimpan {{ $savedAt }}</span>
                @endif
                <span wire:loading.delay class="text-xs text-slate-400">Menyimpan…</span>

                @if ($canEdit)
                    @if ($meeting->status === \App\Enums\MeetingStatus::Terjadwal)
                        <x-btn variant="secondary" icon="pencil-square" :href="route('meetings.edit', $meeting->id)" wire:navigate>Ubah</x-btn>
                        <x-btn variant="warning" icon="arrow-uturn-left" wire:click="cancelMeeting" confirm="Rapat ini akan ditandai batal dan tidak dapat dimulai atau diubah lagi." confirm-title="Batalkan rapat?" confirm-button="Ya, batalkan rapat">Batalkan</x-btn>
                        <x-btn icon="play" wire:click="startMeeting">Mulai Rapat</x-btn>
                    @elseif ($meeting->status === \App\Enums\MeetingStatus::Berlangsung)
                        <x-btn variant="secondary" icon="pencil-square" :href="route('meetings.edit', $meeting->id)" wire:navigate>Ubah agenda/peserta</x-btn>
                        <x-btn variant="danger" icon="stop" wire:click="endMeeting" confirm="Peserta yang belum presensi akan ditandai alpa, lalu notulen menunggu pengesahan." confirm-title="Akhiri rapat?">Akhiri Rapat</x-btn>
                    @endif
                @endif
                @if ($meeting->status === \App\Enums\MeetingStatus::Selesai && $canApprove)
                    <x-btn icon="check-badge" wire:click="approve" confirm="Setelah disahkan, notulen terkunci dan tidak dapat diubah lagi." confirm-title="Sahkan notulen?" confirm-button="Sahkan">Sahkan Notulen</x-btn>
                @endif
                @if ($meeting->status === \App\Enums\MeetingStatus::Disahkan && $isAdmin)
                    <x-btn variant="warning" icon="lock-open" wire:click="reopen" confirm="Pengesahan dibatalkan sehingga notulen dapat diubah kembali, lalu perlu disahkan ulang." confirm-title="Buka kunci notulen?">Buka kunci</x-btn>
                @endif
                <x-btn variant="secondary" icon="printer" :href="route('meetings.print', $meeting->id)" target="_blank">Cetak / PDF</x-btn>
                <x-btn variant="secondary" icon="arrow-down-tray" :href="route('meetings.excel', $meeting->id)" title="Unduh notulen, daftar hadir, keputusan & RTL dalam format Excel">Excel</x-btn>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="mt-5 flex gap-1 overflow-x-auto border-b border-slate-200 text-sm font-semibold">
            @php
                $tabs = ['notulen' => 'Notulen', 'presensi' => 'Presensi ('.$attendance['present'].'/'.$attendance['total'].')', 'rtl' => 'RTL ('.$allActions->count().')'];
                if ($canManage) {
                    $tabs['riwayat'] = 'Riwayat';
                }
            @endphp
            @foreach ($tabs as $key => $label)
                <button type="button" x-on:click="tab = '{{ $key }}'@if ($key === 'riwayat'); $dispatch('meeting-history-refresh')@endif"
                        :class="tab === '{{ $key }}' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        class="-mb-px border-b-2 px-4 py-2 whitespace-nowrap">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- ============ TAB NOTULEN ============ --}}
    <div x-show="tab === 'notulen'" class="grid gap-5 lg:grid-cols-12">
        {{-- Daftar agenda --}}
        <aside class="lg:col-span-4 xl:col-span-3">
            <div class="card p-2 lg:sticky lg:top-6">
                <p class="px-3 pt-2 pb-1 text-xs font-bold tracking-wide text-slate-400 uppercase">Agenda</p>
                <ol class="flex gap-1 overflow-x-auto lg:block lg:space-y-1">
                    @foreach ($agendas as $a)
                        @php $dc = $a->decisions->count(); @endphp
                        <li wire:key="nav-{{ $a->id }}" class="shrink-0">
                            <button type="button" wire:click="selectAgenda({{ $a->id }})"
                                    @class(['flex w-full items-start gap-2 rounded-xl px-3 py-2 text-left text-sm transition',
                                            'bg-brand-50 text-brand-800 ring-1 ring-brand-200' => $a->id === $activeAgendaId,
                                            'text-slate-700 hover:bg-slate-50' => $a->id !== $activeAgendaId])>
                                <span class="font-bold text-slate-400">{{ $loop->iteration }}.</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block lg:truncate">{{ $a->title }}</span>
                                    <span class="flex gap-2 text-[11px] text-slate-400">
                                        @if ($a->is_carry_over) <span>↻ {{ $carryOver->count() }} RTL</span> @endif
                                        @if (filled($discussions[$a->id] ?? null)) <span>✎ dicatat</span> @endif
                                        @if ($dc) <span>{{ $dc }} keputusan</span> @endif
                                        @if ($a->attachments->isNotEmpty()) <span>{{ $a->attachments->count() }} lampiran</span> @endif
                                    </span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ol>
            </div>
        </aside>

        {{-- Isi agenda aktif --}}
        <section class="space-y-5 lg:col-span-8 xl:col-span-9">
            @if ($activeAgenda)
                <div class="card p-5">
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-lg font-semibold text-slate-900">{{ $activeAgenda->title }}</h2>
                        <p class="text-xs text-slate-500">
                            @if ($activeAgenda->presenter) Penyaji: {{ $activeAgenda->presenter->name }} @endif
                            @if ($activeAgenda->duration_minutes) · {{ $activeAgenda->duration_minutes }} menit @endif
                        </p>
                    </div>

                    {{-- Evaluasi RTL rapat sebelumnya --}}
                    @if ($activeAgenda->is_carry_over)
                        <div class="mb-4 rounded-xl border border-brand-100 bg-brand-50/50 p-4">
                            <p class="mb-3 text-sm font-semibold text-brand-800">RTL dari rapat sebelumnya yang belum selesai</p>
                            <div class="space-y-3">
                                @forelse ($carryOver as $item)
                                    <div wire:key="carry-{{ $item->id }}" class="rounded-xl border border-slate-200 bg-white p-3">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-800">{{ $item->title }}</p>
                                                <p class="text-xs text-slate-500">PIC: {{ $item->pic->name }} · Deadline {{ $item->due_date->translatedFormat('d M Y') }} · dari “{{ $item->meeting->title }}”</p>
                                                @include('livewire.partials.evidence-links', ['files' => $item->evidenceAttachments])
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="badge {{ $item->status->badge() }}">{{ $item->status->label() }} · {{ $item->progress }}%</span>
                                                @if ($item->isOverdue()) <span class="badge bg-rose-100 text-rose-700">{{ $item->daysLeftLabel() }}</span> @endif
                                            </div>
                                        </div>
                                        @foreach ($item->updates as $u)
                                            <p class="mt-2 rounded-lg bg-slate-50 px-2 py-1 text-xs text-slate-600">Dicatat di rapat ini: {{ $u->status->label() }} {{ $u->progress }}%{{ $u->note ? ' — '.$u->note : '' }}</p>
                                        @endforeach
                                        @if ($canEdit)
                                            <div class="mt-3 grid gap-2 sm:grid-cols-12">
                                                <select wire:model="carry.{{ $item->id }}.status" class="input sm:col-span-3">
                                                    @foreach (\App\Enums\ActionStatus::cases() as $s) <option value="{{ $s->value }}">{{ $s->label() }}</option> @endforeach
                                                </select>
                                                <div class="flex items-center gap-2 sm:col-span-3">
                                                    <input type="number" min="0" max="100" wire:model="carry.{{ $item->id }}.progress" class="input">
                                                    <span class="text-sm text-slate-500">%</span>
                                                </div>
                                                <input type="text" wire:model="carry.{{ $item->id }}.note" placeholder="Catatan evaluasi…" class="input sm:col-span-4">
                                                <x-btn icon="check" wire:click="saveCarry({{ $item->id }})" class="sm:col-span-2">Catat</x-btn>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">Tidak ada RTL tertunda dari rapat sebelumnya. Alhamdulillah.</p>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    <label class="label">Pembahasan</label>
                    @if ($canEdit)
                        <textarea wire:key="disc-{{ $activeAgenda->id }}" wire:model.live.debounce.1500ms="discussions.{{ $activeAgenda->id }}" rows="9"
                                  class="input leading-relaxed" placeholder="Catat poin-poin pembahasan, pendapat, dan masukan penting… (tersimpan otomatis)"></textarea>
                    @else
                        <div class="min-h-[4rem] rounded-xl bg-slate-50 p-3 text-sm leading-relaxed whitespace-pre-line text-slate-700">{{ $discussions[$activeAgenda->id] ?? '' ?: '— Tidak ada catatan —' }}</div>
                    @endif

                    @include('livewire.partials.agenda-attachments')
                </div>

                {{-- Keputusan & RTL --}}
                <div class="card p-5">
                    <h3 class="mb-3 font-semibold text-slate-900">Keputusan & Rencana Tindak Lanjut</h3>

                    <div class="space-y-4">
                        @forelse ($activeAgenda->decisions as $decision)
                            <div wire:key="dec-{{ $decision->id }}" class="rounded-xl border border-slate-200 p-4">
                                @if ($editingDecisionId === $decision->id)
                                    <textarea wire:model="editingDecisionText" rows="2" class="input"></textarea>
                                    @error('editingDecisionText') <p class="error">{{ $message }}</p> @enderror
                                    <div class="mt-2 flex justify-end gap-2">
                                        <x-btn variant="warning" size="sm" icon="arrow-uturn-left" wire:click="cancelEditing">Batal</x-btn>
                                        <x-btn size="sm" icon="check" wire:click="updateDecision">Simpan</x-btn>
                                    </div>
                                @else
                                    <div class="flex items-start gap-3">
                                        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-600 text-xs font-bold text-white">✓</span>
                                        <p class="flex-1 text-sm font-medium text-slate-800">{{ $decision->content }}</p>
                                        @if ($canEdit)
                                            <div class="flex shrink-0 gap-1">
                                                <x-btn variant="secondary" size="sm" icon="pencil-square" wire:click="editDecision({{ $decision->id }})">Ubah</x-btn>
                                                <x-btn variant="danger" size="sm" icon="trash" wire:click="deleteDecision({{ $decision->id }})" confirm="Keputusan ini beserta semua RTL-nya akan dihapus permanen." confirm-title="Hapus keputusan?">Hapus</x-btn>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                {{-- RTL milik keputusan --}}
                                <div class="mt-3 space-y-2 pl-9">
                                    @foreach ($decision->actionItems as $item)
                                        @if ($editingRtlId === $item->id)
                                            @include('livewire.partials.rtl-form')
                                        @else
                                            <div wire:key="rtl-{{ $item->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                                <span class="flex-1 font-medium text-slate-700">→ {{ $item->title }}</span>
                                                <span class="text-xs text-slate-500">👤 {{ $item->pic->name }}</span>
                                                <span @class(['text-xs', 'font-semibold text-rose-600' => $item->isOverdue(), 'text-slate-500' => ! $item->isOverdue()])>⏰ {{ $item->due_date->translatedFormat('d M Y') }}</span>
                                                <span class="badge {{ $item->priority->badge() }}">{{ $item->priority->label() }}</span>
                                                <span class="badge {{ $item->status->badge() }}">{{ $item->status->label() }}</span>
                                                @if ($canEdit)
                                                    <x-btn variant="secondary" size="sm" icon="pencil-square" wire:click="editRtl({{ $item->id }})" title="Ubah RTL" aria-label="Ubah RTL" />
                                                    <x-btn variant="danger" size="sm" icon="trash" wire:click="deleteRtl({{ $item->id }})" confirm="RTL ini beserta riwayat progresnya akan dihapus permanen." confirm-title="Hapus RTL?" confirm-button="Hapus" title="Hapus RTL" aria-label="Hapus RTL" />
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach

                                    @if ($canEdit)
                                        @if ($rtlDecisionId === $decision->id && ! $editingRtlId)
                                            @include('livewire.partials.rtl-form')
                                        @elseif ($editingDecisionId !== $decision->id)
                                            <x-btn size="sm" icon="clipboard-document-check" wire:click="openRtlForm({{ $decision->id }})">Tambah RTL</x-btn>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">Belum ada keputusan pada agenda ini.</p>
                        @endforelse
                    </div>

                    @if ($canEdit)
                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <label class="label">Keputusan baru</label>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <textarea wire:model="newDecision" rows="2" class="input flex-1" placeholder="Tuliskan keputusan yang disepakati forum…"></textarea>
                                <x-btn icon="document-plus" wire:click="addDecision" class="sm:self-end">Catat Keputusan</x-btn>
                            </div>
                            @error('newDecision') <p class="error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                {{-- Navigasi agenda --}}
                @php
                    $idx = $agendas->search(fn ($a) => $a->id === $activeAgenda->id);
                    $prev = $idx > 0 ? $agendas[$idx - 1] : null;
                    $next = $agendas[$idx + 1] ?? null;
                @endphp
                <div class="flex justify-between">
                    @if ($prev) <x-btn variant="secondary" icon="arrow-left" wire:click="selectAgenda({{ $prev->id }})" :title="$prev->title">{{ \Illuminate\Support\Str::limit($prev->title, 30) }}</x-btn> @else <span></span> @endif
                    @if ($next) <x-btn variant="secondary" icon="arrow-right" wire:click="selectAgenda({{ $next->id }})" :title="$next->title">{{ \Illuminate\Support\Str::limit($next->title, 30) }}</x-btn> @endif
                </div>
            @else
                <div class="card p-10 text-center text-slate-400">Rapat ini belum memiliki agenda.</div>
            @endif

            <div class="card p-5">
                <label class="label">Catatan umum / penutup</label>
                @if ($canEdit)
                    <textarea wire:model.live.debounce.1500ms="notes" rows="3" class="input" placeholder="Catatan tambahan, mis. rapat ditutup pukul … dengan doa kafaratul majelis."></textarea>
                @else
                    <p class="text-sm whitespace-pre-line text-slate-700">{{ $notes ?: '—' }}</p>
                @endif
            </div>
        </section>
    </div>

    {{-- ============ TAB PRESENSI ============ --}}
    <div x-show="tab === 'presensi'" x-cloak class="grid gap-5 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-semibold text-slate-900">Daftar Hadir</h2>
                    <p class="text-sm text-slate-500">{{ $attendance['present'] }} dari {{ $attendance['total'] }} peserta hadir ({{ $attendance['percent'] }}%)</p>
                </div>
                @if ($canEdit)
                    <x-btn size="sm" icon="user-group" wire:click="setAllPresent">Tandai semua hadir</x-btn>
                @endif
            </div>

            <div class="divide-y divide-slate-100">
                @foreach ($participants as $p)
                    <div wire:key="p-{{ $p->id }}" class="flex flex-wrap items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-800">
                                {{ $p->displayName() }}
                                @if ($p->user_id === $meeting->chair_id) <span class="badge bg-brand-100 text-brand-700">Pimpinan</span> @endif
                                @if ($p->user_id && $p->user_id === $meeting->secretary_id) <span class="badge bg-sky-100 text-sky-700">Notulis</span> @endif
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ $p->displayInfo() }}
                                @if ($p->checked_in_at) · presensi {{ $p->checked_in_at->format('H:i') }} @endif
                            </p>
                        </div>
                        @if ($canEdit)
                            <div class="flex flex-wrap gap-1">
                                @foreach (\App\Enums\AttendanceStatus::selectable() as $s)
                                    <button wire:click="setAttendance({{ $p->id }}, '{{ $s->value }}')"
                                            @class(['rounded-lg px-2.5 py-1 text-xs font-semibold transition',
                                                    $s->badge().' ring-2 ring-offset-1 ring-current' => $p->attendance_status === $s,
                                                    'bg-slate-100 text-slate-500 hover:bg-slate-200' => $p->attendance_status !== $s])>{{ $s->label() }}</button>
                                @endforeach
                            </div>
                            @if (! $p->attendance_status->isPresent() && $p->attendance_status !== \App\Enums\AttendanceStatus::Belum)
                                <input type="text" value="{{ $p->note }}" placeholder="Keterangan…"
                                       x-on:change="$wire.updateParticipantNote({{ $p->id }}, $event.target.value)"
                                       class="input w-full sm:w-48">
                            @endif
                            @if (! in_array($p->user_id, [$meeting->chair_id, $meeting->secretary_id], true))
                                <x-btn variant="danger" size="sm" icon="trash" wire:click="removeParticipant({{ $p->id }})" confirm="{{ $p->displayName() }} akan dihapus dari daftar peserta rapat ini." confirm-title="Hapus peserta?" confirm-button="Hapus" title="Hapus peserta" aria-label="Hapus peserta" />
                            @endif
                        @else
                            <span class="badge {{ $p->attendance_status->badge() }}">{{ $p->attendance_status->label() }}</span>
                            @if ($p->note) <span class="text-xs text-slate-500">({{ $p->note }})</span> @endif
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($canEdit)
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <p class="label">Tambah tamu / peserta luar</p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input type="text" wire:model="guestName" placeholder="Nama" class="input">
                        <input type="text" wire:model="guestInfo" placeholder="Instansi / keterangan" class="input">
                        <x-btn icon="user-plus" wire:click="addGuest">Tambah tamu</x-btn>
                    </div>
                    @error('guestName') <p class="error">{{ $message }}</p> @enderror
                </div>
            @endif
        </section>

        @if (in_array($meeting->status, [\App\Enums\MeetingStatus::Terjadwal, \App\Enums\MeetingStatus::Berlangsung], true))
            <section class="card h-fit p-5 text-center">
                <h2 class="font-semibold text-slate-900">Presensi Mandiri (QR)</h2>
                <p class="mb-4 text-xs text-slate-500">Tampilkan di layar. Peserta memindai dengan HP lalu login, presensi tercatat otomatis pada hari rapat.</p>
                <div wire:ignore x-data x-init="window.QRCode && QRCode.toCanvas($refs.qr, @js($checkinUrl), { width: 220, margin: 1 })" class="flex justify-center">
                    <canvas x-ref="qr" class="rounded-xl border border-slate-200"></canvas>
                </div>
                <div x-data="{ copied: false }" class="mt-4">
                    <x-btn variant="secondary" size="sm" icon="clipboard" x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($checkinUrl) }}); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-show="! copied">Salin tautan presensi</span>
                        <span x-show="copied" x-cloak>Tersalin</span>
                    </x-btn>
                </div>
            </section>
        @endif
    </div>

    {{-- ============ TAB RTL ============ --}}
    <div x-show="tab === 'rtl'" x-cloak class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3">Rencana Tindak Lanjut</th>
                        <th class="px-4 py-3">Penanggung Jawab</th>
                        <th class="px-4 py-3">Deadline</th>
                        <th class="px-4 py-3">Prioritas</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Progres</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($allActions as $item)
                        <tr wire:key="all-{{ $item->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">{{ $item->title }}</p>
                                @if ($item->decision) <p class="text-xs text-slate-400">Keputusan: {{ \Illuminate\Support\Str::limit($item->decision->content, 70) }}</p> @endif
                                @include('livewire.partials.evidence-links', ['files' => $item->evidenceAttachments])
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $item->pic->name }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $item->due_date->translatedFormat('d M Y') }}
                                <p @class(['text-xs', 'text-rose-600 font-semibold' => $item->isOverdue(), 'text-slate-400' => ! $item->isOverdue()])>{{ $item->daysLeftLabel() }}</p>
                            </td>
                            <td class="px-4 py-3"><span class="badge {{ $item->priority->badge() }}">{{ $item->priority->label() }}</span></td>
                            <td class="px-4 py-3"><span class="badge {{ $item->status->badge() }}">{{ $item->status->label() }}</span></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-20 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ $item->progress }}%"></div></div>
                                    <span class="text-xs text-slate-500">{{ $item->progress }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Belum ada RTL. Tambahkan dari setiap keputusan di tab Notulen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="border-t border-slate-100 px-4 py-3 text-xs text-slate-500">Penanggung jawab memperbarui progres dari menu <a href="{{ route('actions.mine') }}" wire:navigate class="font-semibold text-brand-700">Tugas Saya</a>. RTL yang belum selesai otomatis muncul pada agenda “Evaluasi RTL” di rapat berikutnya dengan jenis yang sama.</p>
    </div>

    {{-- ============ TAB RIWAYAT (log aktivitas, hanya pengelola rapat) ============ --}}
    @if ($canManage)
        <div x-show="tab === 'riwayat'" x-cloak>
            <livewire:meeting-history :meeting-id="$meeting->id" lazy wire:key="history-{{ $meeting->id }}" />
        </div>
    @endif
</div>
