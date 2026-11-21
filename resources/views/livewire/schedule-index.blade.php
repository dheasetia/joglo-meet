<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Jadwal Rapat Rutin</h1>
            <p class="text-sm text-slate-500">Rapat dibuat otomatis setiap pagi untuk beberapa hari ke depan, lengkap dengan agenda dan peserta.</p>
        </div>
        <x-btn :href="route('schedules.create')" wire:navigate icon="plus">Jadwal Baru</x-btn>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($schedules as $s)
            @php $next = $s->is_active ? $s->nextOccurrence() : null; $made = $upcoming[$s->id] ?? collect(); @endphp
            <div wire:key="s-{{ $s->id }}" @class(['card flex flex-col p-5', 'opacity-70' => ! $s->is_active])>
                <div class="mb-2 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-semibold text-slate-900">{{ $s->title }}</h2>
                        <p class="text-xs text-slate-500">{{ $s->type?->name ?? 'Rapat umum' }}{{ $s->unit ? ' · '.$s->unit->name : '' }}</p>
                    </div>
                    <span @class(['badge', 'bg-emerald-100 text-emerald-700' => $s->is_active, 'bg-slate-200 text-slate-600' => ! $s->is_active])>{{ $s->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </div>

                <div class="space-y-1.5 text-sm text-slate-600">
                    <p class="flex items-center gap-2"><x-heroicon-o-arrow-path class="size-4 shrink-0 text-slate-400" /> {{ $s->describe() }}</p>
                    @if ($s->location) <p class="flex items-center gap-2"><x-heroicon-o-map-pin class="size-4 shrink-0 text-slate-400" /> {{ $s->location }}</p> @endif
                    <p class="flex items-center gap-2"><x-heroicon-o-user class="size-4 shrink-0 text-slate-400" /> {{ $s->chair?->name }}@if ($s->secretary) · notulis {{ $s->secretary->name }}@endif · {{ count($s->participant_ids ?? []) }} peserta tetap</p>
                    <p class="flex items-center gap-2"><x-heroicon-o-calendar-days class="size-4 shrink-0 text-slate-400" />
                        Berlaku {{ $s->starts_on->translatedFormat('d M Y') }} – {{ $s->ends_on ? $s->ends_on->translatedFormat('d M Y') : 'seterusnya' }} · dibuat {{ $s->lead_days }} hari sebelumnya
                    </p>
                </div>

                <div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    @if ($next)
                        Rapat berikutnya: <b class="text-slate-800">{{ \App\Models\MeetingSchedule::DAYS[$next->dayOfWeekIso] }}, {{ $next->translatedFormat('d M Y') }}</b>
                    @elseif ($s->is_active)
                        Tidak ada rapat lagi dalam masa berlaku jadwal.
                    @else
                        Jadwal nonaktif — rapat tidak dibuat otomatis.
                    @endif
                    · {{ $s->meetings_count }} rapat sudah dibuat
                    @if ($made->isNotEmpty())
                        <span class="mt-1 flex flex-wrap gap-1.5">
                            @foreach ($made->take(4) as $m)
                                <a href="{{ route('meetings.show', $m->id) }}" wire:navigate class="rounded-md bg-white px-2 py-0.5 font-medium text-brand-700 shadow-sm hover:underline">{{ $m->date->translatedFormat('d M') }}</a>
                            @endforeach
                        </span>
                    @endif
                </div>

                <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                    <x-btn variant="secondary" size="sm" icon="pencil-square" :href="route('schedules.edit', $s->id)" wire:navigate>Ubah</x-btn>
                    @if ($s->is_active)
                        <x-btn variant="warning" size="sm" icon="pause" wire:click="toggleActive({{ $s->id }})"
                               confirm="Rapat tidak lagi dibuat otomatis dari jadwal ini. Rapat yang sudah dibuat tetap ada." confirm-title="Nonaktifkan jadwal?" confirm-button="Nonaktifkan">Nonaktifkan</x-btn>
                    @else
                        <x-btn size="sm" icon="play" wire:click="toggleActive({{ $s->id }})">Aktifkan</x-btn>
                    @endif
                    <x-btn variant="danger" size="sm" icon="trash" wire:click="delete({{ $s->id }})"
                           confirm="Jadwal “{{ $s->title }}” dihapus. Rapat yang sudah dibuat dari jadwal ini tetap ada." confirm-title="Hapus jadwal?" confirm-button="Hapus">Hapus</x-btn>
                </div>
            </div>
        @empty
            <div class="card col-span-full p-10 text-center">
                <x-heroicon-o-arrow-path class="mx-auto size-10 text-slate-300" />
                <p class="mt-2 font-semibold text-slate-700">Belum ada jadwal rutin</p>
                <p class="mb-4 text-sm text-slate-500">Buat jadwal untuk rapat pekanan/bulanan agar tidak perlu membuat rapat satu per satu.</p>
                <x-btn :href="route('schedules.create')" wire:navigate icon="plus">Jadwal Baru</x-btn>
            </div>
        @endforelse
    </div>
</div>
