<?php

namespace App\Services\WhatsApp;

/** Normalisasi nomor WhatsApp Indonesia ke format internasional tanpa "+": 628xxxxxxxxx. */
class Phone
{
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        $digits = match (true) {
            $digits === '' => '',
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };

        // Nomor seluler Indonesia: 62 8xx, total 10–15 digit
        return preg_match('/^628\d{7,12}$/', $digits) ? $digits : null;
    }

    /** Untuk tampilan: 6281234567890 → 0812-3456-7890 */
    public static function display(?string $phone): string
    {
        if (! $phone) {
            return '—';
        }
        $local = '0'.substr($phone, 2);

        return trim(chunk_split($local, 4, '-'), '-');
    }
}
