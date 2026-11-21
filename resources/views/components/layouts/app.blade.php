<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}Manajemen Rapat</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/logo-joglo-quran-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#592635">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('components.layouts.upload-helper')
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">
@php
    $user = auth()->user();
    $nav = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['route' => 'meetings.index', 'match' => 'meetings.*', 'label' => 'Rapat', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['route' => 'actions.index', 'match' => 'actions.index', 'label' => 'Papan RTL', 'icon' => 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2'],
        ['route' => 'actions.mine', 'match' => 'actions.mine', 'label' => 'Tugas Saya', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
    ];
    if ($user->isAdmin()) {
        $nav[] = ['route' => 'reports.attendance', 'match' => 'reports.*', 'label' => 'Laporan', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'];
    }
@endphp

<div class="flex min-h-screen">
    {{-- Sidebar (desktop) --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-gradient-to-b from-brand-800 to-brand-950 px-4 py-6 lg:flex">
        <a href="{{ route('dashboard') }}" class="mb-8 flex items-center gap-3 px-2">
            <img src="{{ asset('images/logo-joglo-quran-192.png') }}" alt="Logo Pesantren Joglo Qur'an" class="size-11 shrink-0 rounded-full ring-2 ring-gold-300/40">
            <span class="leading-tight">
                <span class="block text-sm font-bold text-white">Manajemen Rapat</span>
                <span class="block text-xs text-gold-300/90">{{ config('rapat.institution') }}</span>
            </span>
        </a>
        <nav class="flex-1 space-y-1">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}" wire:navigate @class(['nav-link', 'nav-link-active' => request()->routeIs($item['match'])])>
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
            @if ($user->isAdmin())
                <a href="/admin" class="nav-link">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Data Master
                </a>
            @endif
        </nav>
        <div class="mt-6 rounded-2xl bg-white/10 p-3">
            <div class="flex items-center gap-3">
                <span class="grid size-9 place-items-center rounded-full bg-gold-300/20 text-sm font-bold text-gold-200">{{ $user->initials() }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
                    <p class="truncate text-xs text-gold-300/80">{{ $user->role->label() }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <x-btn type="submit" variant="secondary" size="sm" icon="arrow-right-on-rectangle" class="w-full">Keluar</x-btn>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        {{-- Topbar (mobile) --}}
        <header class="sticky top-0 z-20 flex items-center justify-between border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur lg:hidden">
            <span class="flex items-center gap-2 text-sm font-bold text-brand-800"><img src="{{ asset('images/logo-joglo-quran-192.png') }}" alt="" class="size-8 rounded-full"> Manajemen Rapat</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<x-btn type="submit" variant="secondary" size="sm" icon="arrow-right-on-rectangle">Keluar</x-btn></form>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 pt-6 pb-24 sm:px-6 lg:px-8 lg:pb-10">
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Bottom nav (mobile) --}}
<nav class="fixed inset-x-0 bottom-0 z-30 grid border-t border-slate-200 bg-white lg:hidden" style="grid-template-columns: repeat({{ count($nav) }}, minmax(0, 1fr))">
    @foreach ($nav as $item)
        <a href="{{ route($item['route']) }}" wire:navigate @class(['flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium', 'text-brand-700' => request()->routeIs($item['match']), 'text-slate-500' => ! request()->routeIs($item['match'])])>
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>

{{-- Toast --}}
<div x-data="{ show: false, message: '', type: 'success', timer: null }"
     @if (session('toast')) x-init="message = @js(session('toast')); show = true; timer = setTimeout(() => show = false, 3000)" @endif
     x-on:toast.window="message = $event.detail.message ?? $event.detail[0]?.message ?? ''; type = $event.detail.type ?? $event.detail[0]?.type ?? 'success'; show = true; clearTimeout(timer); timer = setTimeout(() => show = false, 3000)"
     x-show="show" x-cloak x-transition.opacity
     class="fixed right-4 bottom-20 z-50 lg:bottom-6">
    <div class="flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-medium text-white shadow-lg"
         :class="type === 'error' ? 'bg-rose-600' : 'bg-slate-900'">
        <span x-text="message"></span>
    </div>
</div>
</body>
</html>
