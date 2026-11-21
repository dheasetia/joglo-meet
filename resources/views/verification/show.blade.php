{{-- Verifikasi tanda tangan digital notulen (publik). Variabel: $state, $code, $signature, $newer --}}
@php
    $meeting = $signature?->meeting;
    $tones = [
        'sah' => ['bg-emerald-100 text-emerald-700', 'check-badge', 'Notulen SAH', 'Notulen ini benar disahkan melalui sistem Manajemen Rapat dan isinya tidak berubah sejak disahkan.'],
        'dicabut' => ['bg-amber-100 text-amber-700', 'exclamation-triangle', 'Tidak berlaku', 'Pengesahan dengan kode ini telah dibatalkan. Dokumen yang memuat kode ini tidak berlaku lagi.'],
        'berubah' => ['bg-rose-100 text-rose-700', 'exclamation-triangle', 'Tidak berlaku', 'Isi notulen di sistem berbeda dengan saat disahkan. Hubungi sekretariat untuk dokumen yang berlaku.'],
        'tidak-ditemukan' => ['bg-rose-100 text-rose-700', 'x-circle', 'Kode tidak ditemukan', 'Kode verifikasi ini tidak terdaftar. Periksa kembali penulisan kode, atau dokumen ini tidak diterbitkan oleh sistem kami.'],
    ];
@endphp
<x-layouts.guest title="Verifikasi Notulen">
    <div class="w-full max-w-lg">
        <div class="mb-5 flex items-center justify-center gap-3 text-white">
            <img src="{{ asset('images/logo-joglo-quran-192.png') }}" alt="" class="size-12 rounded-full ring-2 ring-gold-300/40">
            <div class="leading-tight">
                <p class="text-sm font-bold">{{ config('rapat.institution') }}</p>
                <p class="text-xs text-gold-300">Verifikasi Notulen Rapat</p>
            </div>
        </div>

        <div class="card overflow-hidden">
            @if ($state !== 'form')
                @php [$badge, $icon, $heading, $message] = $tones[$state]; @endphp
                <div class="flex items-start gap-4 border-b border-slate-100 p-6">
                    <span class="grid size-14 shrink-0 place-items-center rounded-full {{ $badge }}">
                        <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-8" />
                    </span>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900">{{ $heading }}</h1>
                        <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $message }}</p>
                    </div>
                </div>

                @if ($meeting)
                    <dl class="divide-y divide-slate-100 text-sm">
                        @foreach ([
                            'Rapat' => $meeting->isConfidential() ? 'Rapat bersifat rahasia' : $meeting->title,
                            'Jenis rapat' => $meeting->type?->name ?? 'Rapat umum',
                            'Unit' => $meeting->unit?->name,
                            'Tanggal rapat' => $meeting->date->translatedFormat('l, d F Y'),
                            'Pimpinan rapat' => $meeting->chair?->name,
                            'Notulis' => $meeting->secretary?->name,
                            'Disahkan oleh' => $signature->signer?->name,
                            'Waktu pengesahan' => $signature->signed_at->translatedFormat('d F Y, H:i').' WIB',
                            'Dibatalkan' => $signature->isRevoked() ? $signature->revoked_at->translatedFormat('d F Y, H:i').' WIB'.($signature->revoker ? ' oleh '.$signature->revoker->name : '') : null,
                        ] as $label => $value)
                            @if (filled($value))
                                <div class="grid grid-cols-3 gap-3 px-6 py-2.5">
                                    <dt class="text-slate-500">{{ $label }}</dt>
                                    <dd @class(['col-span-2 font-medium text-slate-800', 'italic text-slate-500' => $label === 'Rapat' && $meeting->isConfidential()])>{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                        <div class="grid grid-cols-3 gap-3 px-6 py-2.5">
                            <dt class="text-slate-500">Kode verifikasi</dt>
                            <dd class="col-span-2 font-mono font-semibold tracking-wide text-slate-800">{{ $signature->code }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-3 px-6 py-2.5">
                            <dt class="text-slate-500">Sidik jari</dt>
                            <dd class="col-span-2 font-mono text-xs break-all text-slate-500" title="SHA-256 isi notulen saat disahkan">{{ $signature->content_hash }}</dd>
                        </div>
                    </dl>

                    @if ($newer)
                        <div class="border-t border-slate-100 bg-emerald-50/60 px-6 py-3 text-sm text-emerald-800">
                            Notulen ini sudah disahkan ulang dengan kode baru
                            <a href="{{ $newer->url() }}" class="font-mono font-semibold underline">{{ $newer->code }}</a>.
                        </div>
                    @endif
                @endif
            @else
                <div class="p-6">
                    <h1 class="text-xl font-bold text-slate-900">Verifikasi notulen</h1>
                    <p class="mt-1 text-sm text-slate-600">Pindai QR pada notulen yang dicetak, atau ketik kode verifikasi di bawah tanda tangan.</p>
                </div>
            @endif

            <form method="GET" action="{{ route('verification.index') }}" class="flex gap-2 border-t border-slate-100 bg-slate-50 p-4">
                <label for="kode" class="sr-only">Kode verifikasi</label>
                <input id="kode" name="kode" value="{{ $state === 'tidak-ditemukan' ? $code : '' }}" placeholder="JQ-XXXX-XXXX-XXXX" required
                       class="input font-mono uppercase" autocomplete="off" spellcheck="false">
                <x-btn type="submit" variant="secondary" icon="magnifying-glass">Periksa</x-btn>
            </form>
        </div>

        <p class="mt-4 text-center text-xs text-emerald-100/70">Halaman ini hanya menyatakan keabsahan dokumen; isi notulen tidak ditampilkan.</p>
    </div>
</x-layouts.guest>
