<?php

namespace App\Exports;

use App\Enums\AttendanceStatus;
use App\Support\Excel\ExcelWorkbook;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Ekspor Excel "Rekap kehadiran": sheet Rekap (per orang) + sheet Detail (per orang per rapat). */
class AttendanceExport
{
    /**
     * @param  Collection<int, object>  $rows  hasil AttendanceReport::reportRows()
     * @param  Collection<int, object>  $details  hasil AttendanceReport::detailRows()
     */
    public static function build(Collection $rows, Collection $details, array $filters = []): ExcelWorkbook
    {
        $book = ExcelWorkbook::make('Rekap Kehadiran Rapat')
            ->sheet('Rekap', [5, 30, 18, 24, 10, 8, 10, 7, 7, 7, 11])
            ->title('Rekap Kehadiran Rapat', [...$filters, 'Diurutkan dari persentase kehadiran terendah'])
            ->header(['No', 'Nama', 'Unit', 'Jabatan', 'Undangan', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa', '% Kehadiran']);

        foreach ($rows->values() as $i => $r) {
            $book->row([$i + 1, $r->user->name, $r->user->unit?->name, $r->user->jabatan,
                $r->invited, $r->hadir, $r->terlambat, $r->izin, $r->sakit, $r->alpa, $r->percent]);
        }

        if ($rows->isEmpty()) {
            $book->note('Belum ada data kehadiran pada periode ini.');
        } else {
            $book->blank()->row(['', 'Jumlah', '', '',
                $rows->sum('invited'), $rows->sum('hadir'), $rows->sum('terlambat'), $rows->sum('izin'),
                $rows->sum('sakit'), $rows->sum('alpa'),
                $rows->sum('invited') ? (int) round(($rows->sum('hadir') + $rows->sum('terlambat')) / $rows->sum('invited') * 100) : 0,
            ], bold: true);
        }

        $book->sheet('Detail', [28, 12, 8, 40, 16, 16, 30])
            ->title('Detail Kehadiran per Rapat', $filters)
            ->header(['Nama', 'Tanggal', 'Jam', 'Rapat', 'Kehadiran', 'Waktu presensi', 'Keterangan']);

        foreach ($details as $d) {
            $book->row([
                $d->user_name,
                Carbon::parse($d->date)->startOfDay(),
                substr((string) $d->start_time, 0, 5),
                $d->title,
                // "belum" pada rapat yang sudah berjalan dihitung alpa
                $d->attendance_status === AttendanceStatus::Belum ? 'Alpa (tidak presensi)' : $d->attendance_status->label(),
                $d->checked_in_at ? Carbon::parse($d->checked_in_at) : null,
                $d->note,
            ]);
        }

        return $book;
    }
}
