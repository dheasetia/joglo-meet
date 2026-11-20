<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Pimpinan = 'pimpinan';
    case Notulis = 'notulis';
    case KepalaUnit = 'kepala_unit';
    case Peserta = 'peserta';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Pimpinan => 'Pimpinan',
            self::Notulis => 'Notulis / Sekretaris',
            self::KepalaUnit => 'Kepala Unit',
            self::Peserta => 'Peserta',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
