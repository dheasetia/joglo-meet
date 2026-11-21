<?php

namespace App\Support;

use App\Models\MeetingType;

/**
 * Susunan agenda bawaan rapat: pembukaan, evaluasi RTL rapat sebelumnya,
 * agenda inti dari template jenis rapat, lalu penutup (config/rapat.php).
 */
class AgendaTemplate
{
    /**
     * @return array<int, array{title: string, is_carry_over: bool}>
     */
    public static function for(?MeetingType $type, bool $withBlankCore = true): array
    {
        $core = $type?->default_agendas ?: ($withBlankCore ? [''] : []);

        $titles = array_merge(
            config('rapat.opening_agendas', []),
            [config('rapat.carry_over_agenda')],
            $core,
            config('rapat.closing_agendas', []),
        );

        return collect($titles)->map(fn ($t) => [
            'title' => (string) $t,
            'is_carry_over' => $t === config('rapat.carry_over_agenda'),
        ])->values()->all();
    }
}
