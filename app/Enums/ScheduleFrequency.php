<?php

namespace App\Enums;

enum ScheduleFrequency: string
{
    case Mingguan = 'mingguan';
    case DuaMingguan = 'dua_mingguan';
    case Bulanan = 'bulanan';

    public function label(): string
    {
        return match ($this) {
            self::Mingguan => 'Setiap pekan',
            self::DuaMingguan => 'Dua pekan sekali',
            self::Bulanan => 'Setiap bulan',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
