<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}">
    <title>Notulen – {{ $meeting->title }} – {{ $meeting->date->format('Y-m-d') }}</title>
    <style>
        @page { size: A4; margin: 18mm 18mm 20mm; }
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Times, serif; font-size: 12pt; color: #111; line-height: 1.45; margin: 0; }
        .wrap { max-width: 800px; margin: 0 auto; padding: 24px; }
        .kop { display: flex; align-items: center; gap: 16px; border-bottom: 3px double #111; padding-bottom: 10px; margin-bottom: 18px; text-align: center; }
        .kop div { flex: 1; }
        .kop img { width: 78px; height: 78px; flex: none; }
        .kop .spacer { width: 78px; flex: none; }
        .kop h1 { margin: 0; font-size: 17pt; letter-spacing: .5px; text-transform: uppercase; }
        .kop p { margin: 2px 0 0; font-size: 10.5pt; }
        h2.title { text-align: center; font-size: 14pt; margin: 0 0 14px; text-decoration: underline; text-transform: uppercase; }
        h3 { font-size: 12pt; margin: 18px 0 6px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 2px 4px; vertical-align: top; }
        .info td:first-child { width: 150px; }
        .grid th, .grid td { border: 1px solid #444; padding: 4px 6px; font-size: 11pt; vertical-align: top; }
        .grid th { background: #f0f0f0; text-align: left; }
        .agenda { margin: 10px 0 12px; page-break-inside: avoid; }
        .agenda h4 { margin: 0 0 4px; font-size: 12pt; }
        .discussion { white-space: pre-line; margin: 0 0 6px; }
        ol.decisions { margin: 4px 0 0; padding-left: 22px; }
        ol.decisions li { margin-bottom: 3px; }
        .rtl { font-size: 10.5pt; color: #333; margin: 2px 0 0 0; padding-left: 14px; }
        .muted { color: #666; font-style: italic; }
        .sign { display: flex; justify-content: space-between; margin-top: 36px; text-align: center; page-break-inside: avoid; }
        .sign div { width: 45%; }
        .sign .space { height: 70px; }
        .toolbar { position: sticky; top: 0; background: #fbf3f5; border-bottom: 1px solid #edc8d0; padding: 10px; text-align: center; font-family: system-ui, sans-serif; font-size: 14px; }
        .toolbar button { display: inline-flex; align-items: center; gap: 6px; background: #334155; color: #fff; border: 0; border-radius: 12px; padding: 8px 14px; font: 600 14px system-ui, sans-serif; cursor: pointer; box-shadow: 0 1px 2px rgb(0 0 0 / .05); transition: transform .15s ease-out, box-shadow .15s ease-out, background-color .15s ease-out; }
        .toolbar button:hover { background: #1e293b; transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgb(0 0 0 / .1), 0 2px 4px -2px rgb(0 0 0 / .1); }
        .toolbar button:active { transform: translateY(0) scale(.98); }
        .toolbar button:focus-visible { outline: none; box-shadow: 0 0 0 2px #fff, 0 0 0 4px #475569; }
        .toolbar button svg { width: 16px; height: 16px; }
        @media (prefers-reduced-motion: reduce) { .toolbar button { transition: none; } .toolbar button:hover { transform: none; } }
        .watermark { color: #b91c1c; font-weight: bold; text-align: center; margin-bottom: 8px; font-family: system-ui, sans-serif; font-size: 10pt; }
        @media print { .toolbar { display: none; } .wrap { padding: 0; } }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">@svg('heroicon-o-printer', ['aria-hidden' => 'true']) Cetak / Simpan sebagai PDF</button>
    <span style="margin-left: 8px; color:#592635">Pilih “Save as PDF” pada dialog cetak untuk menyimpan berkas PDF.</span>
</div>

<div class="wrap">
    <div class="kop">
        <img src="{{ asset('images/logo-joglo-quran.png') }}" alt="Logo">
        <div>
            <h1>{{ config('rapat.institution') }}</h1>
            <p>{{ config('rapat.address') }}</p>
            <p><i>{{ config('rapat.tagline') }}</i></p>
        </div>
        <span class="spacer"></span>
    </div>

    @if ($meeting->status !== \App\Enums\MeetingStatus::Disahkan)
        <p class="watermark">DRAF — notulen belum disahkan</p>
    @endif
    @if ($meeting->isConfidential())
        <p class="watermark">RAHASIA — hanya untuk peserta rapat</p>
    @endif

    <h2 class="title">Notulen Rapat</h2>

    <table class="info">
        <tr><td>Nama rapat</td><td>: {{ $meeting->title }}</td></tr>
        @if ($meeting->type) <tr><td>Jenis rapat</td><td>: {{ $meeting->type->name }}</td></tr> @endif
        <tr><td>Hari, tanggal</td><td>: {{ $meeting->date->translatedFormat('l, d F Y') }}</td></tr>
        <tr><td>Waktu</td><td>: {{ $meeting->started_at?->format('H:i') ?? substr($meeting->start_time, 0, 5) }} – {{ $meeting->ended_at?->format('H:i') ?? ($meeting->end_time ? substr($meeting->end_time, 0, 5) : 'selesai') }} WIB</td></tr>
        <tr><td>Tempat</td><td>: {{ $meeting->location ?? '-' }}{{ $meeting->online_link ? ' (daring: '.$meeting->online_link.')' : '' }}</td></tr>
        <tr><td>Pimpinan rapat</td><td>: {{ $meeting->chair->name }}</td></tr>
        <tr><td>Notulis</td><td>: {{ $meeting->secretary?->name ?? '-' }}</td></tr>
        @php $sum = $meeting->attendanceSummary(); @endphp
        <tr><td>Kehadiran</td><td>: {{ $sum['present'] }} dari {{ $sum['total'] }} peserta ({{ $sum['percent'] }}%)</td></tr>
    </table>

    <h3>A. Agenda Rapat</h3>
    <ol style="margin:0; padding-left:22px">
        @foreach ($meeting->agendas as $a)
            <li>{{ $a->title }}@if ($a->presenter) <span class="muted">({{ $a->presenter->name }})</span>@endif</li>
        @endforeach
    </ol>

    <h3>B. Pembahasan dan Keputusan</h3>
    @foreach ($meeting->agendas as $a)
        <div class="agenda">
            <h4>{{ $loop->iteration }}. {{ $a->title }}</h4>

            @if ($a->is_carry_over)
                @if ($carryOver->isEmpty())
                    <p class="muted">Tidak ada RTL tertunda dari rapat sebelumnya.</p>
                @else
                    <table class="grid" style="margin-bottom:6px">
                        <tr><th>RTL rapat sebelumnya</th><th style="width:22%">PIC</th><th style="width:28%">Hasil evaluasi</th></tr>
                        @foreach ($carryOver as $item)
                            @php $u = $item->updates->first(); @endphp
                            <tr>
                                <td>{{ $item->title }}<br><span class="muted" style="font-size:9.5pt">deadline {{ $item->due_date->translatedFormat('d M Y') }}</span></td>
                                <td>{{ $item->pic->name }}</td>
                                <td>{{ ($u?->status ?? $item->status)->label() }} ({{ $u?->progress ?? $item->progress }}%){{ $u?->note ? ' — '.$u->note : '' }}@if ($item->evidenceAttachments->isNotEmpty())<br><span class="muted" style="font-size:9.5pt">Bukti: {{ $item->evidenceAttachments->pluck('original_name')->implode('; ') }}</span>@endif</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            @endif

            @if (filled($a->discussion))
                <p class="discussion">{{ $a->discussion }}</p>
            @elseif (! $a->is_carry_over)
                <p class="muted">—</p>
            @endif

            @if ($a->attachments->isNotEmpty())
                <p class="muted" style="margin:0 0 6px">Lampiran: {{ $a->attachments->map(fn ($f) => $f->original_name.' ('.$f->humanSize().')')->implode('; ') }}</p>
            @endif

            @if ($a->decisions->isNotEmpty())
                <b>Keputusan:</b>
                <ol class="decisions">
                    @foreach ($a->decisions as $d)
                        <li>{{ $d->content }}
                            @foreach ($d->actionItems as $item)
                                <div class="rtl">→ RTL: {{ $item->title }} — PIC: {{ $item->pic->name }}, deadline {{ $item->due_date->translatedFormat('d M Y') }}</div>
                            @endforeach
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    @endforeach

    @if (filled($meeting->notes))
        <p><b>Catatan:</b> {{ $meeting->notes }}</p>
    @endif

    <h3>C. Rencana Tindak Lanjut</h3>
    @if ($meeting->actionItems->isEmpty())
        <p class="muted">Tidak ada rencana tindak lanjut.</p>
    @else
        <table class="grid">
            <tr><th style="width:5%">No</th><th>Rencana tindak lanjut</th><th style="width:22%">Penanggung jawab</th><th style="width:16%">Deadline</th><th style="width:11%">Prioritas</th></tr>
            @foreach ($meeting->actionItems->sortBy('due_date')->values() as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->title }}@if ($item->success_indicator)<br><span class="muted" style="font-size:9.5pt">Indikator: {{ $item->success_indicator }}</span>@endif</td>
                    <td>{{ $item->pic->name }}</td>
                    <td>{{ $item->due_date->translatedFormat('d M Y') }}</td>
                    <td>{{ $item->priority->label() }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h3>D. Daftar Hadir</h3>
    <table class="grid">
        <tr><th style="width:5%">No</th><th>Nama</th><th style="width:28%">Jabatan / Unit</th><th style="width:16%">Kehadiran</th><th style="width:22%">Keterangan</th></tr>
        @foreach ($meeting->participants->sortBy(fn ($p) => [$p->user_id ? 0 : 1, $p->displayName()])->values() as $i => $p)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $p->displayName() }}</td>
                <td>{{ $p->displayInfo() }}</td>
                <td>{{ $p->attendance_status->label() }}{{ $p->checked_in_at ? ' ('.$p->checked_in_at->format('H:i').')' : '' }}</td>
                <td>{{ $p->note }}</td>
            </tr>
        @endforeach
    </table>

    <div class="sign">
        <div>
            <p>Notulis,</p>
            <div class="space"></div>
            <p><b><u>{{ $meeting->secretary?->name ?? '...........................' }}</u></b></p>
        </div>
        <div>
            <p>{{ config('rapat.city') }}, {{ ($meeting->approved_at ?? $meeting->date)->translatedFormat('d F Y') }}<br>Mengesahkan, Pimpinan Rapat</p>
            <div class="space" style="display:flex;align-items:center;justify-content:center;font-family:system-ui,sans-serif;font-size:9pt;color:#047857">
                @if ($meeting->approved_at) ✓ Disahkan secara elektronik<br>{{ $meeting->approved_at->format('d/m/Y H:i') }} @endif
            </div>
            <p><b><u>{{ $meeting->chair->name }}</u></b></p>
        </div>
    </div>
</div>
</body>
</html>
