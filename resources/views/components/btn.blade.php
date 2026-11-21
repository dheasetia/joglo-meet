{{--
    Tombol standar aplikasi rapat.

    <x-btn variant="primary" icon="plus" wire:click="addAgenda">Tambah agenda</x-btn>
    <x-btn variant="secondary" icon="pencil-square" :href="route('meetings.edit', $id)" wire:navigate>Ubah</x-btn>
    <x-btn variant="danger" icon="trash" size="sm" title="Hapus" wire:click="remove(1)" />   (ikon saja → wajib title)

    Dengan konfirmasi (modal, bukan alert browser):
    <x-btn variant="danger" icon="trash" wire:click="deleteRtl(5)"
           confirm="RTL ini akan dihapus." confirm-title="Hapus RTL?" confirm-button="Hapus">Hapus</x-btn>

    variant        : primary | secondary | danger | warning
    icon           : nama heroicon outline tanpa awalan, mis. "plus" → <x-heroicon-o-plus>
    size           : md | sm
    href           : bila diisi dirender sebagai <a>, selain itu <button type="button">
    confirm        : pesan konfirmasi; bila diisi, wire:click baru dijalankan setelah dikonfirmasi di modal
    confirm-title  : judul modal (bawaan: label tombol + "?")
    confirm-button : label tombol konfirmasi (bawaan: label tombol)
--}}
@props([
    'variant' => 'primary',
    'icon' => null,
    'size' => 'md',
    'href' => null,
    'confirm' => null,
    'confirmTitle' => null,
    'confirmButton' => null,
])

@php
    $iconOnly = $slot->isEmpty();

    $variants = [
        'primary' => 'bg-emerald-700 hover:bg-emerald-800 focus-visible:ring-emerald-600',
        'secondary' => 'bg-slate-700 hover:bg-slate-800 focus-visible:ring-slate-600',
        'danger' => 'bg-rose-700 hover:bg-rose-800 focus-visible:ring-rose-600',
        'warning' => 'bg-amber-600 hover:bg-amber-700 focus-visible:ring-amber-500',
    ];

    $sizes = $iconOnly
        ? ['md' => 'p-2 rounded-xl', 'sm' => 'p-1.5 rounded-lg']
        : ['md' => 'px-3.5 py-2 text-sm gap-1.5 rounded-xl', 'sm' => 'px-2.5 py-1.5 text-xs gap-1 rounded-lg'];

    $classes = implode(' ', [
        'inline-flex shrink-0 items-center justify-center font-semibold whitespace-nowrap text-white shadow-sm select-none',
        // animasi halus
        'transition duration-150 ease-out hover:-translate-y-0.5 hover:shadow-md active:translate-y-0 active:scale-[0.98]',
        'motion-reduce:transition-none motion-reduce:hover:translate-y-0 motion-reduce:active:scale-100',
        // fokus keyboard
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
        // nonaktif: redup & tanpa animasi hover
        'disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);

    $iconClass = $size === 'sm' ? 'size-3.5 shrink-0' : 'size-4 shrink-0';

    if ($confirm) {
        $label = trim(strip_tags((string) $slot)) ?: $attributes->get('title') ?: 'Lanjutkan';
        $action = $attributes->get('wire:click');
        $dialogId = 'konfirmasi-'.substr(md5($action.'|'.$confirm), 0, 10);
        $tone = [
            'danger' => ['bg-rose-100 text-rose-700', 'exclamation-triangle'],
            'warning' => ['bg-amber-100 text-amber-700', 'exclamation-triangle'],
            'primary' => ['bg-emerald-100 text-emerald-700', 'question-mark-circle'],
            'secondary' => ['bg-slate-100 text-slate-700', 'question-mark-circle'],
        ][$variant] ?? ['bg-slate-100 text-slate-700', 'question-mark-circle'];
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon) <x-dynamic-component :component="'heroicon-o-'.$icon" :class="$iconClass" aria-hidden="true" /> @endif
        {{ $slot }}
    </a>
@elseif ($confirm)
    {{-- Tombol pemicu hanya membuka modal; aksi wire:click dijalankan oleh tombol di dalam modal --}}
    <span x-data="{ confirmOpen: false }" class="contents">
        <button {{ $attributes->except(['wire:click', 'wire:confirm'])->merge(['type' => 'button'])->class($classes) }}
                x-on:click="confirmOpen = true" aria-haspopup="dialog" aria-controls="{{ $dialogId }}">
            @if ($icon) <x-dynamic-component :component="'heroicon-o-'.$icon" :class="$iconClass" aria-hidden="true" /> @endif
            {{ $slot }}
        </button>

        @teleport('body')
            <div id="{{ $dialogId }}" x-show="confirmOpen" x-cloak
                 class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center sm:p-4"
                 role="alertdialog" aria-modal="true" aria-labelledby="{{ $dialogId }}-judul" aria-describedby="{{ $dialogId }}-pesan"
                 x-on:keydown.escape.window="confirmOpen = false">
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px]" x-show="confirmOpen"
                     x-transition.opacity.duration.150ms x-on:click="confirmOpen = false"></div>

                <div x-show="confirmOpen" x-trap.noscroll.inert="confirmOpen"
                     x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:leave="transition duration-100 ease-in" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="relative w-full max-w-md rounded-t-2xl bg-white p-6 text-left shadow-xl sm:rounded-2xl">
                    <div class="flex items-start gap-4">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full {{ $tone[0] }}">
                            <x-dynamic-component :component="'heroicon-o-'.$tone[1]" class="size-6" aria-hidden="true" />
                        </span>
                        <div class="min-w-0 pt-0.5">
                            <h2 id="{{ $dialogId }}-judul" class="text-base font-semibold text-slate-900">{{ $confirmTitle ?? $label.'?' }}</h2>
                            <p id="{{ $dialogId }}-pesan" class="mt-1 text-sm leading-relaxed whitespace-normal text-slate-600">{{ $confirm }}</p>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <x-btn variant="secondary" icon="arrow-left" x-on:click="confirmOpen = false" class="w-full sm:w-auto">Batal</x-btn>
                        <x-btn :variant="$variant" :icon="$icon ?? 'check'" wire:click="{{ $action }}" x-on:click="confirmOpen = false" class="w-full sm:w-auto">{{ $confirmButton ?? $label }}</x-btn>
                    </div>
                </div>
            </div>
        @endteleport
    </span>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class($classes) }}>
        @if ($icon) <x-dynamic-component :component="'heroicon-o-'.$icon" :class="$iconClass" aria-hidden="true" /> @endif
        {{ $slot }}
    </button>
@endif
