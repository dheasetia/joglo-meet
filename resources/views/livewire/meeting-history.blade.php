<div class="card p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="font-semibold text-slate-900">Riwayat Aktivitas</h2>
            <p class="text-sm text-slate-500">Siapa melakukan apa pada rapat ini, terbaru di atas.</p>
        </div>
        <x-btn variant="secondary" size="sm" icon="arrow-path" wire:click="$refresh" wire:loading.attr="disabled">Muat ulang</x-btn>
    </div>

    @forelse ($days as $day => $logs)
        <div wire:key="day-{{ \Illuminate\Support\Str::slug($day) }}" class="mb-6 last:mb-0">
            <p class="mb-3 text-xs font-bold tracking-wide text-slate-400 uppercase">{{ $day }}</p>

            <ol class="relative space-y-4 border-l border-slate-200 pl-5">
                @foreach ($logs as $log)
                    @php
                        $lines = $log->changeLines();
                        $dot = match (strtok($log->description, ' ')) {
                            'menghapus', 'membatalkan' => 'bg-rose-500',
                            'mengesahkan', 'memulai' => 'bg-emerald-500',
                            'mengakhiri', 'membuka' => 'bg-amber-500',
                            default => 'bg-slate-300',
                        };
                    @endphp
                    <li wire:key="log-{{ $log->id }}" class="relative">
                        <span class="absolute top-1 -left-[27px] size-3 rounded-full ring-4 ring-white {{ $dot }}"></span>

                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 text-sm">
                            <span class="font-semibold text-slate-800">{{ $log->causerName() }}</span>
                            <span class="text-slate-600">{{ $log->description }}</span>
                            @if ($log->subjectLabel() && class_basename((string) $log->subject_type) !== 'Meeting')
                                <span class="font-medium text-slate-800">“{{ $log->subjectLabel() }}”</span>
                            @endif
                        </div>
                        <div class="mt-0.5 flex items-center gap-2 text-xs text-slate-400">
                            <time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->translatedFormat('d F Y H:i:s') }}">{{ $log->created_at->format('H:i') }}</time>
                            <span class="badge bg-slate-100 text-slate-500">{{ $log->logLabel() }}</span>
                        </div>

                        @if ($lines)
                            <dl class="mt-2 space-y-1 rounded-lg bg-slate-50 px-3 py-2 text-xs">
                                @foreach ($lines as $line)
                                    <div class="flex flex-wrap gap-x-1.5">
                                        <dt class="font-semibold text-slate-500">{{ $line['field'] }}:</dt>
                                        <dd class="text-slate-700">
                                            @if ($line['old'] !== null && $line['new'] !== null)
                                                <span class="text-slate-400 line-through">{{ $line['old'] }}</span> → {{ $line['new'] }}
                                            @elseif ($line['new'] !== null)
                                                {{ $line['new'] }}
                                            @else
                                                <span class="text-slate-400 line-through">{{ $line['old'] }}</span>
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    @empty
        <p class="py-10 text-center text-sm text-slate-400">Belum ada aktivitas yang tercatat untuk rapat ini.</p>
    @endforelse

    @if ($hasMore)
        <div class="mt-4 text-center">
            <x-btn variant="secondary" size="sm" icon="chevron-down" wire:click="loadMore">Tampilkan lebih banyak</x-btn>
        </div>
    @endif
</div>
