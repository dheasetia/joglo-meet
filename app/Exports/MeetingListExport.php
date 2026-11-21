<?php

namespace App\Exports;

use App\Models\Meeting;
use App\Support\Excel\ExcelWorkbook;
use Illuminate\Database\Eloquent\Builder;

/** Ekspor Excel "Daftar rapat" sesuai filter di halaman /rapat. */
class MeetingListExport
{
    /**
     * @param  Builder<Meeting>  $query  query yang sudah difilter & dibatasi hak akses
     * @param  array<int, string>  $filters  keterangan filter untuk subjudul
     */
    public static function build(Builder $query, array $filters = []): ExcelWorkbook
    {
        $book = ExcelWorkbook::make('Daftar Rapat')
            ->sheet('Daftar rapat', [5, 12, 13, 40, 26, 14, 26, 26, 20, 10, 10, 10, 11, 9])
            ->title('Daftar Rapat', [$filters ? 'Filter: '.implode(' · ', $filters) : 'Semua rapat'])
            ->header(['No', 'Tanggal', 'Waktu', 'Judul rapat', 'Jenis rapat', 'Unit', 'Pimpinan rapat', 'Notulis', 'Status',
                'Peserta', 'Hadir', '% Hadir', 'Keputusan', 'RTL']);

        $no = 0;
        $query->clone()
            ->with(['type', 'unit', 'chair', 'secretary', 'participants'])
            ->withCount(['decisions', 'actionItems'])
            ->orderByDesc('date')->orderByDesc('start_time')
            ->chunk(200, function ($meetings) use ($book, &$no) {
                foreach ($meetings as $meeting) {
                    $sum = $meeting->attendanceSummary();
                    $book->row([
                        ++$no,
                        $meeting->date,
                        $meeting->timeRange(),
                        $meeting->title.($meeting->isConfidential() ? ' (rahasia)' : ''),
                        $meeting->type?->name ?? 'Rapat umum',
                        $meeting->unit?->name,
                        $meeting->chair?->name,
                        $meeting->secretary?->name,
                        $meeting->status->label(),
                        $sum['total'],
                        $sum['present'],
                        $sum['total'] ? $sum['percent'] : null,
                        $meeting->decisions_count,
                        $meeting->action_items_count,
                    ]);
                }
            });

        if ($no === 0) {
            $book->note('Tidak ada rapat yang sesuai filter.');
        }

        return $book;
    }
}
