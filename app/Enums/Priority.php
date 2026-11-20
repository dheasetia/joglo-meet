<?php

namespace App\Enums;

enum Priority: string
{
    case Rendah = 'rendah';
    case Sedang = 'sedang';
    case Tinggi = 'tinggi';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Rendah => 'bg-slate-100 text-slate-600',
            self::Sedang => 'bg-sky-100 text-sky-700',
            self::Tinggi => 'bg-rose-100 text-rose-700',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
