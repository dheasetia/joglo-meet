<x-layouts.guest title="Presensi Rapat">
    <div class="card w-full max-w-sm p-6 text-center">
        <div @class(['mx-auto mb-4 grid size-16 place-items-center rounded-full', 'bg-emerald-100 text-emerald-600' => $ok, 'bg-rose-100 text-rose-600' => ! $ok])>
            @if ($ok)
                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            @else
                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            @endif
        </div>
        <h1 class="text-lg font-bold text-slate-800">{{ $meeting->title }}</h1>
        <p class="mb-4 text-sm text-slate-500">{{ $meeting->date->translatedFormat('l, d F Y') }} · {{ $meeting->timeRange() }}</p>
        <p class="mb-6 text-sm font-medium {{ $ok ? 'text-emerald-700' : 'text-rose-700' }}">{{ $message }}</p>
        <a href="{{ route('dashboard') }}" class="btn-secondary w-full">Ke Dashboard</a>
    </div>
</x-layouts.guest>
