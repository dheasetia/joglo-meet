<?php

namespace App\Enums;

enum ActionStatus: string
{
    case Belum = 'belum';
    case Proses = 'proses';
    case Tertunda = 'tertunda';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public function label(): string
    {
        return match ($this) {
            self::Belum => 'Belum Mulai',
            self::Proses => 'Proses',
            self::Tertunda => 'Tertunda',
            self::Selesai => 'Selesai',
            self::Batal => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Belum => 'bg-slate-100 text-slate-600',
            self::Proses => 'bg-sky-100 text-sky-700',
            self::Tertunda => 'bg-amber-100 text-amber-800',
            self::Selesai => 'bg-emerald-100 text-emerald-700',
            self::Batal => 'bg-slate-200 text-slate-500 line-through',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, self::openCases(), true);
    }

    /** @return array<int, self> */
    public static function openCases(): array
    {
        return [self::Belum, self::Proses, self::Tertunda];
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
