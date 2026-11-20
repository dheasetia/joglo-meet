<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">{{ now()->translatedFormat('l, d F Y') }}</p>
            <h1 class="text-2xl font-bold text-slate-900">Assalamu'alaikum, {{ auth()->user()->name }}</h1>
        </div>
        @if (auth()->user()->canCreateMeeting())
            <a href="{{ route('meetings.create') }}" wire:navigate class="btn-primary">+ Rapat Baru</a>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @php
            $cards = [
                ['Rapat bulan ini', $stats['meetings'], 'text-slate-900', null],
                ['Kehadiran rata-rata', $stats['attendance'] !== null ? $stats['attendance'].'%' : '–', 'text-emerald-700', 'bulan ini'],
                ['RTL aktif', $stats['open'], 'text-sky-700', null],
                ['RTL terlambat', $stats['overdue'], $stats['overdue'] ? 'text-rose-600' : 'text-slate-900', null],
            ];
        @endphp
        @foreach ($cards as [$label, $value, $color, $hint])
            <div class="card p-4">
                <p class="text-xs font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-3xl font-bold {{ $color }}">{{ $value }}</p>
                @if ($hint) <p class="text-[11px] text-slate-400">{{ $hint }}</p> @endif
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-5">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-semibold text-rose-700">RTL Terlambat</h2>
                    <a href="{{ route('actions.index', ['filter' => 'terlambat']) }}" wire:navigate class="text-xs font-semibold text-emerald-700 hover:underline">Lihat semua</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($overdue as $item)
                        @include('livewire.partials.action-row')
                    @empty
                        <p class="py-6 text-center text-sm text-slate-400">Alhamdulillah, tidak ada RTL yang terlambat.</p>
                    @endforelse
                </div>
            </section>

            <section class="card p-5">
                <h2 class="mb-2 font-semibold text-slate-800">Deadline 7 Hari ke Depan</h2>
                <div class="divide-y divide-slate-100">
                    @forelse ($dueSoon as $item)
                        @include('livewire.partials.action-row')
                    @empty
                        <p class="py-6 text-center text-sm text-slate-400">Tidak ada RTL dengan deadline sepekan ini.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <a href="{{ route('actions.mine') }}" wire:navigate class="card flex items-center justify-between bg-gradient-to-br from-emerald-600 to-emerald-800 p-5 text-white">
                <div>
                    <p class="text-sm text-emerald-100">Tugas saya yang belum selesai</p>
                    <p class="text-3xl font-bold">{{ $myTasks }}</p>
                </div>
                <svg class="size-8 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>

            <section class="card p-5">
                <h2 class="mb-3 font-semibold text-slate-800">Rapat Mendatang</h2>
                <div class="space-y-3">
                    @forelse ($upcoming as $m)
                        <a href="{{ route('meetings.show', $m) }}" wire:navigate class="flex gap-3 rounded-xl p-2 hover:bg-slate-50">
                            <div class="w-12 shrink-0 rounded-xl bg-emerald-50 py-1.5 text-center">
                                <p class="text-[10px] font-semibold uppercase text-emerald-700">{{ $m->date->translatedFormat('M') }}</p>
                                <p class="text-lg leading-none font-bold text-emerald-800">{{ $m->date->format('d') }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-800">{{ $m->title }}</p>
                                <p class="text-xs text-slate-500">{{ $m->date->translatedFormat('l') }}, {{ $m->timeRange() }} · {{ $m->participants_count }} peserta</p>
                                @if ($m->location) <p class="truncate text-xs text-slate-400">{{ $m->location }}</p> @endif
                            </div>
                        </a>
                    @empty
                        <p class="py-4 text-center text-sm text-slate-400">Belum ada rapat terjadwal.</p>
                    @endforelse
                </div>
            </section>

            @if ($pendingApproval->isNotEmpty())
                <section class="card p-5">
                    <h2 class="mb-3 font-semibold text-violet-700">Menunggu Pengesahan</h2>
                    <div class="space-y-2">
                        @foreach ($pendingApproval as $m)
                            <a href="{{ route('meetings.show', $m) }}" wire:navigate class="block rounded-xl p-2 hover:bg-slate-50">
                                <p class="text-sm font-semibold text-slate-800">{{ $m->title }}</p>
                                <p class="text-xs text-slate-500">{{ $m->date->translatedFormat('d M Y') }} · Pimpinan: {{ $m->chair->name }}</p>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
