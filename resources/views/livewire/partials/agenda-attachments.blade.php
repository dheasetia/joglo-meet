{{-- Lampiran agenda aktif di Ruang Rapat. Variabel: $activeAgenda, $canEdit --}}
@php
    $files = $activeAgenda->attachments;
    $images = $files->filter->isImage();
    $documents = $files->reject->isImage();
    $user = auth()->user();
@endphp

<div wire:key="lampiran-{{ $activeAgenda->id }}" class="mt-5 border-t border-slate-100 pt-4"
     x-data="rapatUploads({ property: 'agendaUploads', append: false })"
     @if ($canEdit)
         x-on:dragover.prevent="dragging = true"
         x-on:dragleave.prevent="if (! $el.contains($event.relatedTarget)) dragging = false"
         x-on:drop.prevent="dragging = false; pick($event.dataTransfer.files)"
     @endif>

    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-medium text-slate-700">
            Lampiran
            @if ($files->isNotEmpty()) <span class="font-normal text-slate-400">({{ $files->count() }})</span> @endif
        </p>
        @if ($canEdit)
            <div class="flex items-center gap-2">
                <input type="file" multiple class="sr-only" x-ref="lampiran"
                       x-on:change="pick($event.target.files); $event.target.value = ''"
                       accept=".{{ implode(',.', config('rapat.attachments.mimes')) }}">
                <x-btn size="sm" icon="paper-clip" x-on:click="$refs.lampiran.click()" x-bind:disabled="uploading">Tambah lampiran</x-btn>
            </div>
        @endif
    </div>

    @if ($canEdit)
        <div class="relative rounded-xl border-2 border-dashed transition"
             :class="dragging ? 'border-emerald-500 bg-emerald-50/60' : 'border-transparent'">

            <div x-show="dragging" x-cloak class="pointer-events-none absolute inset-0 z-10 grid place-items-center rounded-xl text-sm font-semibold text-emerald-700">
                Lepaskan berkas untuk mengunggah
            </div>
    @endif

            {{-- Bilah progres unggah --}}
            <div x-show="uploading" x-cloak class="mb-3">
                <div class="mb-1 flex justify-between text-xs text-slate-500"><span>Mengunggah…</span><span x-text="progress + '%'"></span></div>
                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-600 transition-all" :style="`width: ${progress}%`"></div></div>
            </div>

            <p x-show="clientError" x-text="clientError" x-cloak class="error mb-1"></p>
            @foreach ($errors->get('agendaUploads*') as $messages)
                @foreach ((array) $messages as $message) <p class="error mb-1">{{ $message }}</p> @endforeach
            @endforeach

            {{-- Foto: grid thumbnail --}}
            @if ($images->isNotEmpty())
                <div class="mb-3 grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                    @foreach ($images as $file)
                        <div wire:key="att-{{ $file->id }}" class="group relative aspect-square overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                            <a href="{{ $file->url() }}" target="_blank" rel="noopener" title="{{ $file->original_name }} · {{ $file->humanSize() }}">
                                <img src="{{ $file->url() }}" alt="{{ $file->original_name }}" loading="lazy" class="size-full object-cover transition group-hover:scale-105">
                            </a>
                            @if ($file->canDelete($user))
                                <div class="absolute top-1 right-1 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100">
                                    <x-btn variant="danger" size="sm" icon="trash" wire:click="deleteAttachment({{ $file->id }})"
                                           title="Hapus foto" aria-label="Hapus foto {{ $file->original_name }}"
                                           confirm="{{ $file->original_name }} akan dihapus permanen." confirm-title="Hapus lampiran?" confirm-button="Hapus" />
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Dokumen: daftar --}}
            @if ($documents->isNotEmpty())
                <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                    @foreach ($documents as $file)
                        <li wire:key="att-{{ $file->id }}" class="flex items-center gap-3 px-3 py-2">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500">
                                <x-dynamic-component :component="'heroicon-o-'.$file->icon()" class="size-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ $file->url() }}" @if ($file->opensInline()) target="_blank" rel="noopener" @endif
                                   class="block truncate text-sm font-medium text-slate-800 hover:text-brand-700 hover:underline">{{ $file->original_name }}</a>
                                <p class="truncate text-xs text-slate-400">{{ $file->extension() }} · {{ $file->humanSize() }}@if ($file->uploader) · {{ $file->uploader->name }}@endif · {{ $file->created_at->translatedFormat('d M H:i') }}</p>
                            </div>
                            <x-btn variant="secondary" size="sm" icon="arrow-down-tray" :href="$file->url(download: true)" title="Unduh" aria-label="Unduh {{ $file->original_name }}" />
                            @if ($file->canDelete($user))
                                <x-btn variant="danger" size="sm" icon="trash" wire:click="deleteAttachment({{ $file->id }})"
                                       title="Hapus lampiran" aria-label="Hapus {{ $file->original_name }}"
                                       confirm="{{ $file->original_name }} akan dihapus permanen." confirm-title="Hapus lampiran?" confirm-button="Hapus" />
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($files->isEmpty())
                <p class="rounded-xl bg-slate-50 px-3 py-3 text-sm text-slate-400">
                    @if ($canEdit)
                        Belum ada lampiran. Klik <b class="font-semibold text-slate-500">Tambah lampiran</b> atau seret berkas ke sini.
                    @else
                        Tidak ada lampiran.
                    @endif
                </p>
            @endif

            @if ($canEdit)
                <p class="mt-2 text-xs text-slate-400">{{ \App\Support\AttachmentStorage::hint() }} · foto diperkecil otomatis</p>
            @endif

    @if ($canEdit)
        </div>
    @endif
</div>
