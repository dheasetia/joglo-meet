<?php

namespace App\Exports;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Support\Excel\ExcelWorkbook;

/** Ekspor Excel satu rapat: info & agenda, daftar hadir, keputusan & RTL, evaluasi RTL sebelumnya. */
class MeetingExport
{
    public static function build(Meeting $meeting): ExcelWorkbook
    {
        $meeting->loadMissing([
            'type', 'unit', 'chair', 'secretary', 'approver',
            'participants.user.unit',
            'agendas.presenter', 'agendas.attachments', 'agendas.decisions.actionItems.pic',
            'agendas.decisions.actionItems.evidenceAttachments',
        ]);
        $sum = $meeting->attendanceSummary();

        // ---------- Sheet 1: info rapat & agenda ----------
        $book = ExcelWorkbook::make('Notulen '.$meeting->title)
            ->sheet('Info rapat', [24, 34, 22, 10, 60, 50, 30])
            ->title('Notulen Rapat: '.$meeting->title, [
                $meeting->status === MeetingStatus::Disahkan ? 'Disahkan' : 'Draf — belum disahkan',
                $meeting->isConfidential() ? 'RAHASIA — hanya untuk peserta rapat' : null,
            ])
            ->pair('Jenis rapat', $meeting->type?->name ?? 'Rapat umum')
            ->pair('Unit', $meeting->unit?->name)
            ->pair('Tanggal', $meeting->date)
            ->pair('Waktu', $meeting->timeRange().' WIB')
            ->pair('Tempat', $meeting->location)
            ->pair('Tautan daring', $meeting->online_link)
            ->pair('Pimpinan rapat', $meeting->chair?->name)
            ->pair('Notulis', $meeting->secretary?->name)
            ->pair('Status', $meeting->status->label())
            ->pair('Disahkan', $meeting->approved_at ? $meeting->approver?->name.' · '.$meeting->approved_at->translatedFormat('d F Y H:i') : '—')
            ->pair('Kehadiran', "{$sum['present']} dari {$sum['total']} peserta ({$sum['percent']}%)")
            ->pair('Catatan', $meeting->notes)
            ->blank()
            ->header(['No', 'Agenda', 'Penyaji', 'Durasi (menit)', 'Pembahasan', 'Keputusan', 'Lampiran']);

        foreach ($meeting->agendas as $i => $agenda) {
            $book->row([
                $i + 1,
                $agenda->title,
                $agenda->presenter?->name,
                $agenda->duration_minutes,
                $agenda->discussion,
                $agenda->decisions->pluck('content')->map(fn ($c, $n) => ($n + 1).'. '.$c)->implode("\n"),
                $agenda->attachments->pluck('original_name')->implode("\n"),
            ]);
        }

        // ---------- Sheet 2: daftar hadir ----------
        $book->sheet('Daftar hadir', [5, 30, 30, 10, 16, 16, 34])
            ->title('Daftar Hadir: '.$meeting->title, [$meeting->date->translatedFormat('l, d F Y').' · '.$meeting->timeRange()])
            ->header(['No', 'Nama', 'Jabatan / Unit', 'Peserta', 'Kehadiran', 'Waktu presensi', 'Keterangan']);

        $participants = $meeting->participants->sortBy(fn ($p) => [$p->user_id ? 0 : 1, $p->displayName()])->values();
        foreach ($participants as $i => $p) {
            $book->row([
                $i + 1,
                $p->displayName(),
                $p->user ? ($p->user->jabatan ?? $p->user->unit?->name) : $p->guest_info,
                $p->user_id ? 'Undangan' : 'Tamu',
                $p->attendance_status->label(),
                $p->checked_in_at,
                $p->note,
            ]);
        }

        // ---------- Sheet 3: keputusan & RTL ----------
        $book->sheet('Keputusan & RTL', [5, 28, 44, 40, 24, 12, 10, 14, 9, 30, 30])
            ->title('Keputusan & Rencana Tindak Lanjut: '.$meeting->title)
            ->header(['No', 'Agenda', 'Keputusan', 'Rencana tindak lanjut', 'Penanggung jawab', 'Deadline', 'Prioritas', 'Status', 'Progres (%)', 'Indikator selesai', 'Bukti']);

        $no = 0;
        foreach ($meeting->agendas as $agenda) {
            foreach ($agenda->decisions as $decision) {
                $items = $decision->actionItems->isEmpty() ? [null] : $decision->actionItems;
                foreach ($items as $item) {
                    $book->row([
                        ++$no, $agenda->title, $decision->content,
                        $item?->title, $item?->pic?->name, $item?->due_date, $item?->priority->label(),
                        $item?->status->label(), $item?->progress, $item?->success_indicator,
                        $item?->evidenceAttachments->pluck('original_name')->implode("\n"),
                    ]);
                }
            }
        }
        if ($no === 0) {
            $book->note('Belum ada keputusan pada rapat ini.');
        }

        // ---------- Sheet 4: evaluasi RTL rapat sebelumnya (bila ada agendanya) ----------
        if ($meeting->agendas->contains('is_carry_over', true)) {
            $carryOver = $meeting->carryOverActionItems();
            $book->sheet('Evaluasi RTL', [5, 40, 24, 34, 12, 14, 9, 40])
                ->title('Evaluasi RTL Rapat Sebelumnya', ['RTL yang belum selesai dari rapat sejenis sebelumnya'])
                ->header(['No', 'Rencana tindak lanjut', 'Penanggung jawab', 'Dari rapat', 'Deadline', 'Status', 'Progres (%)', 'Hasil evaluasi di rapat ini']);

            foreach ($carryOver->values() as $i => $item) {
                $update = $item->updates->first();
                $book->row([
                    $i + 1, $item->title, $item->pic?->name, $item->meeting?->title, $item->due_date,
                    ($update?->status ?? $item->status)->label(), $update?->progress ?? $item->progress,
                    $update?->note,
                ]);
            }
            if ($carryOver->isEmpty()) {
                $book->note('Tidak ada RTL tertunda dari rapat sebelumnya.');
            }
        }

        return $book;
    }

    public static function filename(Meeting $meeting): string
    {
        return 'notulen-'.$meeting->date->format('Y-m-d').'-'.str($meeting->title)->slug().'.xlsx';
    }
}
