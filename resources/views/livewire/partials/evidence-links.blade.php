{{-- Deretan tautan bukti RTL / lampiran kecil. Variabel: $files (Collection<Attachment>) --}}
@if ($files->isNotEmpty())
    <div class="mt-1.5 flex flex-wrap gap-1.5">
        @foreach ($files as $file)
            <a href="{{ $file->url() }}" @if ($file->opensInline()) target="_blank" rel="noopener" @endif
               title="{{ $file->original_name }} · {{ $file->humanSize() }}"
               class="inline-flex max-w-56 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-600 hover:border-brand-300 hover:text-brand-700">
                <x-dynamic-component :component="'heroicon-o-'.$file->icon()" class="size-3.5 shrink-0" />
                <span class="truncate">{{ $file->original_name }}</span>
            </a>
        @endforeach
    </div>
@endif
