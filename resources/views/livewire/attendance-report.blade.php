<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">Rekap Kehadiran Rapat</h1>
        <x-btn variant="secondary" icon="printer" onclick="window.print()" class="print:hidden">Cetak</x-btn>
    </div>

    <div class="card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4 print:hidden">
        <div><label class="label">Dari</label><input type="date" wire:model.live="from" class="input"></div>
        <div><label class="label">Sampai</label><input type="date" wire:model.live="to" class="input"></div>
        <div>
            <label class="label">Unit peserta</label>
            <select wire:model.live="unit" class="input">
                <option value="">Semua unit</option>
                @foreach ($units as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
            </select>
        </div>
        <div>
            <label class="label">Jenis rapat</label>
            <select wire:model.live="type" class="input">
                <option value="">Semua jenis</option>
                @foreach ($types as $t) <option value="{{ $t->id }}">{{ $t->name }}</option> @endforeach
            </select>
        </div>
    </div>

    <p class="text-sm text-slate-500">Periode {{ \Illuminate\Support\Carbon::parse($from)->translatedFormat('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($to)->translatedFormat('d M Y') }}. Diurutkan dari persentase kehadiran terendah.</p>

    <div class="card overflow-hidden" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3 text-center">Undangan</th>
                        <th class="px-4 py-3 text-center">Hadir</th>
                        <th class="px-4 py-3 text-center">Terlambat</th>
                        <th class="px-4 py-3 text-center">Izin</th>
                        <th class="px-4 py-3 text-center">Sakit</th>
                        <th class="px-4 py-3 text-center">Alpa</th>
                        <th class="px-4 py-3">Kehadiran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $r)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">{{ $r->user->name }}</p>
                                <p class="text-xs text-slate-400">{{ $r->user->unit?->name }}</p>
                            </td>
                            <td class="px-4 py-3 text-center">{{ $r->invited }}</td>
                            <td class="px-4 py-3 text-center text-emerald-700">{{ $r->hadir }}</td>
                            <td class="px-4 py-3 text-center text-amber-700">{{ $r->terlambat }}</td>
                            <td class="px-4 py-3 text-center text-sky-700">{{ $r->izin }}</td>
                            <td class="px-4 py-3 text-center text-violet-700">{{ $r->sakit }}</td>
                            <td class="px-4 py-3 text-center font-semibold text-rose-700">{{ $r->alpa }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-24 overflow-hidden rounded-full bg-slate-100">
                                        <div @class(['h-full rounded-full', 'bg-emerald-500' => $r->percent >= 80, 'bg-amber-400' => $r->percent >= 60 && $r->percent < 80, 'bg-rose-500' => $r->percent < 60]) style="width: {{ $r->percent }}%"></div>
                                    </div>
                                    <span class="text-xs font-semibold">{{ $r->percent }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Belum ada data kehadiran pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
