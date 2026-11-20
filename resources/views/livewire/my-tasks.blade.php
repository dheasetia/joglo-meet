<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Tugas Saya</h1>
        <p class="text-sm text-slate-500">Rencana tindak lanjut rapat yang menjadi tanggung jawab Anda. “Sesungguhnya Allah mencintai jika salah seorang di antara kalian bekerja, ia menyempurnakannya.”</p>
    </div>

    <div class="space-y-3">
        @forelse ($open as $item)
            <div wire:key="t-{{ $item->id }}" @class(['card p-4', 'border-rose-300 bg-rose-50/30' => $item->isOverdue()])>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-800">{{ $item->title }}</p>
                        <a href="{{ route('meetings.show', $item->meeting_id) }}" wire:navigate class="text-xs text-slate-500 hover:text-emerald-700 hover:underline">{{ $item->meeting->title }} · {{ $item->meeting->date->translatedFormat('d M Y') }}</a>
                        @if ($item->success_indicator) <p class="mt-1 text-xs text-emerald-700">🎯 {{ $item->success_indicator }}</p> @endif
                    </div>
                    <span class="badge {{ $item->priority->badge() }}">{{ $item->priority->label() }}</span>
                </div>
                <div class="mt-3 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $item->progress }}%"></div></div>
                    <span class="text-xs font-semibold text-slate-600">{{ $item->progress }}%</span>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <div class="text-xs">
                        <span class="badge {{ $item->status->badge() }}">{{ $item->status->label() }}</span>
                        <span @class(['ml-2', 'font-semibold text-rose-600' => $item->isOverdue(), 'text-slate-500' => ! $item->isOverdue()])>⏰ {{ $item->due_date->translatedFormat('d M Y') }} · {{ $item->daysLeftLabel() }}</span>
                    </div>
                    <button wire:click="openUpdate({{ $item->id }})" class="btn-primary btn-sm">Perbarui</button>
                </div>
            </div>
        @empty
            <div class="card p-10 text-center">
                <p class="text-3xl">🎉</p>
                <p class="mt-2 font-semibold text-slate-700">Tidak ada tugas yang tertunda.</p>
                <p class="text-sm text-slate-500">Alhamdulillah, semua RTL Anda sudah selesai.</p>
            </div>
        @endforelse
    </div>

    <div>
        <button wire:click="$toggle('showDone')" class="text-sm font-semibold text-emerald-700 hover:underline">
            {{ $showDone ? 'Sembunyikan' : 'Tampilkan' }} tugas selesai ({{ $doneCount }})
        </button>
        @if ($showDone)
            <div class="mt-3 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white">
                @foreach ($done as $item)
                    <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                        <span class="text-slate-600">{{ $item->title }}</span>
                        <span class="badge {{ $item->status->badge() }}">{{ $item->daysLeftLabel() }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @include('livewire.partials.action-update-modal')
</div>
