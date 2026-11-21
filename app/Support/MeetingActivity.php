<?php

namespace App\Support;

use App\Models\Meeting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Catatan log aktivitas manual untuk hal yang tidak tertangkap event model:
 * operasi massal (tandai semua hadir, alpa otomatis, simpan form rapat) dan
 * isian yang tersimpan otomatis saat diketik (pembahasan, catatan umum).
 */
class MeetingActivity
{
    /**
     * @param  array<string, string|int|array<int, string>>  $detail  ditampilkan sebagai baris "Label: nilai"
     */
    public static function log(Meeting $meeting, string $logName, string $description, array $detail = [], ?Model $subject = null, ?string $label = null): void
    {
        activity($logName)
            ->performedOn($subject ?? $meeting)
            ->event('custom')
            ->withProperties(array_filter([
                'meeting_id' => $meeting->id,
                'label' => $label ?? ($subject ? null : $meeting->title),
                'detail' => array_filter($detail, fn ($v) => $v !== [] && $v !== '' && $v !== null),
            ]))
            ->log($description);
    }

    /**
     * Catat paling banyak sekali per orang per $key dalam $minutes menit.
     * Dipakai untuk autosave agar log tidak terisi tiap 1,5 detik mengetik.
     */
    public static function throttled(string $key, int $minutes, callable $log): void
    {
        if (Cache::add('activity:'.$key.':'.auth()->id(), true, now()->addMinutes($minutes))) {
            $log();
        }
    }
}
