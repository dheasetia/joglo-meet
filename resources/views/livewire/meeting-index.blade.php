<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">Daftar Rapat</h1>
        <div class="flex flex-wrap gap-2">
            <x-btn variant="secondary" icon="arrow-down-tray" wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel">
                <span wire:loading.remove wire:target="exportExcel">Ekspor Excel</span>
                <span wire:loading wire:target="exportExcel">Menyiapkan…</span>
            </x-btn>
            @if ($canCreate)
                <x-btn :href="route('meetings.create')" wire:navigate icon="plus">Rapat Baru</x-btn>
            @endif
        </div>
    </div>

    <div class="card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Cari judul, pembahasan, keputusan…" class="input lg:col-span-2">
        <select wire:model.live="type" class="input">
            <option value="">Semua jenis</option>
            @foreach ($types as $t) <option value="{{ $t->id }}">{{ $t->name }}</option> @endforeach
        </select>
        <select wire:model.live="status" class="input">
            <option value="">Semua status</option>
            @foreach ($statuses as $s) <option value="{{ $s->value }}">{{ $s->label() }}</option> @endforeach
        </select>
        <input type="date" wire:model.live="from" class="input" title="Dari tanggal">
        <div class="flex gap-2">
            <input type="date" wire:model.live="to" class="input" title="Sampai tanggal">
            <x-btn variant="secondary" icon="x-mark" wire:click="resetFilters" title="Reset filter" aria-label="Reset filter" />
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-60">
        @forelse ($meetings as $m)
            @php $sum = $m->attendanceSummary(); @endphp
            <a href="{{ route('meetings.show', $m) }}" wire:navigate wire:key="m-{{ $m->id }}" class="card group flex flex-col p-5 transition hover:border-brand-300 hover:shadow-md">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <span class="badge {{ $m->status->badge() }}">{{ $m->status->label() }}</span>
                    @if ($m->isConfidential()) <span class="badge bg-slate-800 text-white">Rahasia</span> @endif
                </div>
                <h2 class="font-semibold text-slate-900 group-hover:text-brand-700">{{ $m->title }}</h2>
                <p class="text-xs text-slate-500">{{ $m->type?->name ?? 'Rapat umum' }}{{ $m->unit ? ' · '.$m->unit->name : '' }}</p>
                <div class="mt-3 space-y-1 text-sm text-slate-600">
                    <p>📅 {{ $m->date->translatedFormat('l, d M Y') }} · {{ $m->timeRange() }}</p>
                    @if ($m->location) <p class="truncate">📍 {{ $m->location }}</p> @endif
                    <p class="truncate">👤 {{ $m->chair->name }}</p>
                </div>
                <div class="mt-auto flex gap-4 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <span>{{ $sum['present'] }}/{{ $sum['total'] }} hadir</span>
                    <span>{{ $m->decisions_count }} keputusan</span>
                    <span>{{ $m->action_items_count }} RTL</span>
                </div>
            </a>
        @empty
            <div class="card col-span-full p-10 text-center text-slate-400">Belum ada rapat yang sesuai filter.</div>
        @endforelse
    </div>

    {{ $meetings->links() }}
</div>
