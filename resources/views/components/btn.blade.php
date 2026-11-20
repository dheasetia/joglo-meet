{{--
    Tombol standar aplikasi rapat.

    <x-btn variant="primary" icon="plus" wire:click="addAgenda">Tambah agenda</x-btn>
    <x-btn variant="secondary" icon="pencil-square" :href="route('meetings.edit', $id)" wire:navigate>Ubah</x-btn>
    <x-btn variant="danger" icon="trash" size="sm" title="Hapus" wire:click="remove(1)" />   (ikon saja → wajib title)

    variant : primary | secondary | danger | warning
    icon    : nama heroicon outline tanpa awalan, mis. "plus" → <x-heroicon-o-plus>
    size    : md | sm
    href    : bila diisi dirender sebagai <a>, selain itu <button type="button">
--}}
@props([
    'variant' => 'primary',
    'icon' => null,
    'size' => 'md',
    'href' => null,
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
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon) <x-dynamic-component :component="'heroicon-o-'.$icon" :class="$iconClass" aria-hidden="true" /> @endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class($classes) }}>
        @if ($icon) <x-dynamic-component :component="'heroicon-o-'.$icon" :class="$iconClass" aria-hidden="true" /> @endif
        {{ $slot }}
    </button>
@endif
