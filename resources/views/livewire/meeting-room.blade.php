<div x-data="{ tab: 'notulen' }" class="space-y-5">
    {{-- ============ Header ============ --}}
    <div class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <a href="{{ route('meetings.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-emerald-700">← Daftar rapat</a>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">{{ $meeting->title }}</h1>
                    <span class="badge {{ $meeting->status->badge() }}">{{ $meeting->status->label() }}</span>
                    @if ($meeting->isConfidential()) <span class="badge bg-slate-800 text-white">Rahasia</span> @endif
                </div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                    <span>📅 {{ $meeting->date->translatedFormat('l, d F Y') }}</span>
                    <span>🕐 {{ $meeting->timeRange() }}</span>
                    @if ($meeting->location) <span>📍 {{ $meeting->location }}</span> @endif
                    @if ($meeting->online_link) <a href="{{ $meeting->online_link }}" target="_blank" rel="noopener" class="text-emerald-700 hover:underline">🔗 Tautan daring</a> @endif
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
                        <a href="{{ route('meetings.edit', $meeting->id) }}" wire:navigate class="btn-secondary">Ubah</a>
                        <button wire:click="cancelMeeting" wire:confirm="Batalkan rapat ini?" class="btn-ghost text-rose-600">Batalkan</button>
                        <button wire:click="startMeeting" class="btn-primary">▶ Mulai Rapat</button>
                    @elseif ($meeting->status === \App\Enums\MeetingStatus::Berlangsung)
                        <a href="{{ route('meetings.edit', $meeting->id) }}" wire:navigate class="btn-secondary">Ubah agenda/peserta</a>
                        <button wire:click="endMeeting" wire:confirm="Akhiri rapat? Peserta yang belum presensi akan ditandai alpa." class="btn-danger">■ Akhiri Rapat</button>
                    @endif
                @endif
                @if ($meeting->status === \App\Enums\MeetingStatus::Selesai && $canApprove)
                    <button wire:click="approve" wire:confirm="Sahkan notulen ini? Setelah disahkan, notulen terkunci." class="btn-primary">✓ Sahkan Notulen</button>
                @endif
                @if ($meeting->status === \App\Enums\MeetingStatus::Disahkan && $isAdmin)
                    <button wire:click="reopen" wire:confirm="Batalkan pengesahan agar notulen bisa diubah?" class="btn-ghost">Buka kunci</button>
                @endif
                <a href="{{ route('meetings.print', $meeting->id) }}" target="_blank" class="btn-secondary">🖨 Cetak / PDF</a>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="mt-5 flex gap-1 overflow-x-auto border-b border-slate-200 text-sm font-semibold">
            @foreach (['notulen' => 'Notulen', 'presensi' => 'Presensi ('.$attendance['present'].'/'.$attendance['total'].')', 'rtl' => 'RTL ('.$allActions->count().')'] as $key => $label)
                <button type="button" x-on:click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
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
                                            'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' => $a->id === $activeAgendaId,
                                            'text-slate-700 hover:bg-slate-50' => $a->id !== $activeAgendaId])>
                                <span class="font-bold text-slate-400">{{ $loop->iteration }}.</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block lg:truncate">{{ $a->title }}</span>
                                    <span class="flex gap-2 text-[11px] text-slate-400">
                                        @if ($a->is_carry_over) <span>↻ {{ $carryOver->count() }} RTL</span> @endif
                                        @if (filled($discussions[$a->id] ?? null)) <span>✎ dicatat</span> @endif
                                        @if ($dc) <span>{{ $dc }} keputusan</span> @endif
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
                        <div class="mb-4 rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
                            <p class="mb-3 text-sm font-semibold text-emerald-800">RTL dari rapat sebelumnya yang belum selesai</p>
                            <div class="space-y-3">
                                @forelse ($carryOver as $item)
                                    <div wire:key="carry-{{ $item->id }}" class="rounded-xl border border-slate-200 bg-white p-3">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-800">{{ $item->title }}</p>
                                                <p class="text-xs text-slate-500">PIC: {{ $item->pic->name }} · Deadline {{ $item->due_date->translatedFormat('d M Y') }} · dari “{{ $item->meeting->title }}”</p>
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
                                                <button wire:click="saveCarry({{ $item->id }})" class="btn-primary sm:col-span-2">Catat</button>
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
                                        <button wire:click="cancelEditing" class="btn-ghost btn-sm">Batal</button>
                                        <button wire:click="updateDecision" class="btn-primary btn-sm">Simpan</button>
                                    </div>
                                @else
                                    <div class="flex items-start gap-3">
                                        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-600 text-xs font-bold text-white">✓</span>
                                        <p class="flex-1 text-sm font-medium text-slate-800">{{ $decision->content }}</p>
                                        @if ($canEdit)
                                            <div class="flex shrink-0 gap-1">
                                                <button wire:click="editDecision({{ $decision->id }})" class="btn-ghost btn-sm">Ubah</button>
                                                <button wire:click="deleteDecision({{ $decision->id }})" wire:confirm="Hapus keputusan ini beserta RTL-nya?" class="btn-ghost btn-sm text-rose-600">Hapus</button>
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
                                                    <button wire:click="editRtl({{ $item->id }})" class="text-xs font-semibold text-slate-500 hover:text-emerald-700">Ubah</button>
                                                    <button wire:click="deleteRtl({{ $item->id }})" wire:confirm="Hapus RTL ini?" class="text-xs font-semibold text-rose-500 hover:text-rose-700">Hapus</button>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach

                                    @if ($canEdit)
                                        @if ($rtlDecisionId === $decision->id && ! $editingRtlId)
                                            @include('livewire.partials.rtl-form')
                                        @elseif ($editingDecisionId !== $decision->id)
                                            <button wire:click="openRtlForm({{ $decision->id }})" class="text-xs font-semibold text-emerald-700 hover:underline">+ Tambah RTL</button>
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
                                <button wire:click="addDecision" class="btn-primary sm:self-end">+ Catat Keputusan</button>
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
                    @if ($prev) <button wire:click="selectAgenda({{ $prev->id }})" class="btn-ghost">← {{ \Illuminate\Support\Str::limit($prev->title, 30) }}</button> @else <span></span> @endif
                    @if ($next) <button wire:click="selectAgenda({{ $next->id }})" class="btn-secondary">{{ \Illuminate\Support\Str::limit($next->title, 30) }} →</button> @endif
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
                    <button wire:click="setAllPresent" class="btn-secondary btn-sm">Tandai semua hadir</button>
                @endif
            </div>

            <div class="divide-y divide-slate-100">
                @foreach ($participants as $p)
                    <div wire:key="p-{{ $p->id }}" class="flex flex-wrap items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-800">
                                {{ $p->displayName() }}
                                @if ($p->user_id === $meeting->chair_id) <span class="badge bg-emerald-100 text-emerald-700">Pimpinan</span> @endif
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
                                <button wire:click="removeParticipant({{ $p->id }})" wire:confirm="Hapus {{ $p->displayName() }} dari daftar peserta?" class="text-slate-300 hover:text-rose-600" title="Hapus peserta">✕</button>
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
                        <button wire:click="addGuest" class="btn-secondary shrink-0">+ Tambah</button>
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
                    <button x-on:click="navigator.clipboard.writeText(@js($checkinUrl)); copied = true; setTimeout(() => copied = false, 2000)" class="btn-secondary btn-sm">
                        <span x-show="! copied">Salin tautan presensi</span>
                        <span x-show="copied" x-cloak>Tersalin ✓</span>
                    </button>
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
                                    <div class="h-2 w-20 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $item->progress }}%"></div></div>
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
        <p class="border-t border-slate-100 px-4 py-3 text-xs text-slate-500">Penanggung jawab memperbarui progres dari menu <a href="{{ route('actions.mine') }}" wire:navigate class="font-semibold text-emerald-700">Tugas Saya</a>. RTL yang belum selesai otomatis muncul pada agenda “Evaluasi RTL” di rapat berikutnya dengan jenis yang sama.</p>
    </div>
</div>
