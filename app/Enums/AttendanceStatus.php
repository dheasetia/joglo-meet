<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Belum = 'belum';
    case Hadir = 'hadir';
    case Terlambat = 'terlambat';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Alpa = 'alpa';

    public function label(): string
    {
        return match ($this) {
            self::Belum => 'Belum presensi',
            self::Hadir => 'Hadir',
            self::Terlambat => 'Terlambat',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Alpa => 'Alpa',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Belum => 'bg-slate-100 text-slate-500',
            self::Hadir => 'bg-emerald-100 text-emerald-700',
            self::Terlambat => 'bg-amber-100 text-amber-800',
            self::Izin => 'bg-sky-100 text-sky-700',
            self::Sakit => 'bg-violet-100 text-violet-700',
            self::Alpa => 'bg-rose-100 text-rose-700',
        };
    }

    public function isPresent(): bool
    {
        return in_array($this, [self::Hadir, self::Terlambat], true);
    }

    /** Status yang bisa dipilih notulis (tanpa "belum"). */
    public static function selectable(): array
    {
        return [self::Hadir, self::Terlambat, self::Izin, self::Sakit, self::Alpa];
    }
}
