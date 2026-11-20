{{-- Satu baris RTL ringkas. Variabel: $item --}}
<div class="flex items-start gap-3 py-3">
    <span @class(['mt-1.5 size-2.5 shrink-0 rounded-full', 'bg-rose-500' => $item->isOverdue(), 'bg-amber-400' => ! $item->isOverdue() && $item->status->isOpen(), 'bg-emerald-500' => ! $item->status->isOpen()])></span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-slate-800">{{ $item->title }}</p>
        <p class="truncate text-xs text-slate-500">
            {{ $item->pic->name }} · <a href="{{ route('meetings.show', $item->meeting_id) }}" wire:navigate class="hover:text-brand-700 hover:underline">{{ $item->meeting->title }}</a>
        </p>
    </div>
    <div class="shrink-0 text-right">
        <p @class(['text-xs font-semibold', 'text-rose-600' => $item->isOverdue(), 'text-slate-600' => ! $item->isOverdue()])>{{ $item->daysLeftLabel() }}</p>
        <p class="text-[11px] text-slate-400">{{ $item->due_date->translatedFormat('d M') }} · {{ $item->progress }}%</p>
    </div>
</div>
