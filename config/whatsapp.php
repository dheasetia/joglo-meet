<?php

return [
    /*
    | Pengirim pesan WhatsApp:
    |  - 'fonnte' : kirim sungguhan lewat https://fonnte.com (isi FONNTE_TOKEN)
    |  - 'log'    : hanya dicatat ke storage/logs (uji coba, tanpa mengirim)
    */
    'driver' => env('WHATSAPP_DRIVER', 'log'),

    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
        'url' => env('FONNTE_URL', 'https://api.fonnte.com/send'),
        'timeout' => 20,
    ],

    // Jeda acak antarpesan (detik) agar nomor pengirim tidak dianggap spam
    'delay_min' => (int) env('WHATSAPP_DELAY_MIN', 4),
    'delay_max' => (int) env('WHATSAPP_DELAY_MAX', 9),

    // Jam kirim pengingat harian (rapat besok, deadline RTL besok, RTL terlambat)
    'reminder_time' => env('WHATSAPP_REMINDER_TIME', '07:00'),
];
