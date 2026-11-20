<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Terjadwal = 'terjadwal';
    case Berlangsung = 'berlangsung';
    case Selesai = 'selesai';
    case Disahkan = 'disahkan';
    case Batal = 'batal';

    public function label(): string
    {
        return match ($this) {
            self::Terjadwal => 'Terjadwal',
            self::Berlangsung => 'Berlangsung',
            self::Selesai => 'Menunggu Pengesahan',
            self::Disahkan => 'Disahkan',
            self::Batal => 'Batal',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Terjadwal => 'bg-sky-100 text-sky-700',
            self::Berlangsung => 'bg-amber-100 text-amber-800',
            self::Selesai => 'bg-violet-100 text-violet-700',
            self::Disahkan => 'bg-emerald-100 text-emerald-700',
            self::Batal => 'bg-slate-200 text-slate-600',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
