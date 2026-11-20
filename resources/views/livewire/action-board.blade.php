<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">Papan Rencana Tindak Lanjut</h1>
        <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 text-sm font-semibold">
            <button wire:click="$set('view', 'tabel')" @class(['rounded-lg px-3 py-1.5', 'bg-emerald-600 text-white' => $view === 'tabel', 'text-slate-500' => $view !== 'tabel'])>Tabel</button>
            <button wire:click="$set('view', 'kanban')" @class(['rounded-lg px-3 py-1.5', 'bg-emerald-600 text-white' => $view === 'kanban', 'text-slate-500' => $view !== 'kanban'])>Kanban</button>
        </div>
    </div>

    <div class="card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Cari RTL…" class="input lg:col-span-2">
        <select wire:model.live="pic" class="input">
            <option value="">Semua penanggung jawab</option>
            @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
        </select>
        <select wire:model.live="unit" class="input">
            <option value="">Semua unit</option>
            @foreach ($units as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
        </select>
        <select wire:model.live="type" class="input">
            <option value="">Semua jenis rapat</option>
            @foreach ($types as $t) <option value="{{ $t->id }}">{{ $t->name }}</option> @endforeach
        </select>
    </div>

    @if ($view === 'tabel')
        <div class="flex flex-wrap gap-2">
            @foreach (['aktif' => 'Aktif', 'terlambat' => 'Terlambat', 'selesai' => 'Selesai', 'semua' => 'Semua'] as $key => $label)
                <button wire:click="$set('filter', '{{ $key }}')"
                        @class(['rounded-full px-3.5 py-1.5 text-sm font-semibold transition',
                                'bg-slate-900 text-white' => $filter === $key,
                                'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' => $filter !== $key])>
                    {{ $label }}
                    @isset($counts[$key]) <span class="ml-1 opacity-70">{{ $counts[$key] }}</span> @endisset
                </button>
            @endforeach
        </div>

        <div class="card overflow-hidden" wire:loading.class="opacity-60">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-3">RTL</th>
                            <th class="px-4 py-3">Penanggung Jawab</th>
                            <th class="px-4 py-3">Deadline</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Progres</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($items as $item)
                            <tr wire:key="row-{{ $item->id }}" @class(['bg-rose-50/40' => $item->isOverdue()])>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $item->title }}</p>
                                    <a href="{{ route('meetings.show', $item->meeting_id) }}" wire:navigate class="text-xs text-slate-400 hover:text-emerald-700 hover:underline">{{ $item->meeting->title }} · {{ $item->meeting->date->translatedFormat('d M Y') }}</a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $item->pic->name }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $item->due_date->translatedFormat('d M Y') }}
                                    <p @class(['text-xs', 'font-semibold text-rose-600' => $item->isOverdue(), 'text-slate-400' => ! $item->isOverdue()])>{{ $item->daysLeftLabel() }}</p>
                                </td>
                                <td class="px-4 py-3"><span class="badge {{ $item->status->badge() }}">{{ $item->status->label() }}</span></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-20 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $item->progress }}%"></div></div>
                                        <span class="text-xs text-slate-500">{{ $item->progress }}%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($item->canUpdate($me))
                                        <button wire:click="openUpdate({{ $item->id }})" class="btn-secondary btn-sm">Perbarui</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Tidak ada RTL pada filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $items->links() }}
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($columns as $col)
                <section class="rounded-2xl bg-slate-100/80 p-3">
                    <h2 class="mb-3 flex items-center justify-between px-1 text-sm font-semibold text-slate-700">
                        <span class="badge {{ $col['status']->badge() }}">{{ $col['status']->label() }}</span>
                        <span class="text-xs text-slate-400">{{ $col['items']->count() }}</span>
                    </h2>
                    <div class="space-y-2">
                        @forelse ($col['items'] as $item)
                            <button type="button" wire:key="k-{{ $item->id }}" @if ($item->canUpdate($me)) wire:click="openUpdate({{ $item->id }})" @endif
                                    @class(['card block w-full p-3 text-left transition hover:shadow-md', 'border-rose-300' => $item->isOverdue()])>
                                <p class="text-sm font-medium text-slate-800">{{ $item->title }}</p>
                                <p class="mt-1 truncate text-xs text-slate-400">{{ $item->meeting->title }}</p>
                                <div class="mt-2 flex items-center justify-between text-xs">
                                    <span class="text-slate-600">👤 {{ $item->pic->name }}</span>
                                    <span @class(['font-semibold text-rose-600' => $item->isOverdue(), 'text-slate-500' => ! $item->isOverdue()])>{{ $item->daysLeftLabel() }}</span>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $item->progress }}%"></div></div>
                            </button>
                        @empty
                            <p class="py-6 text-center text-xs text-slate-400">Kosong</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
        <p class="text-xs text-slate-500">Kolom “Selesai” menampilkan RTL yang selesai dalam 30 hari terakhir. Klik kartu untuk memperbarui progres.</p>
    @endif

    @include('livewire.partials.action-update-modal')
</div>
