<?php

return [
    // Kop surat pada cetak notulen
    'institution' => env('RAPAT_INSTITUTION', "Pondok Pesantren Joglo Qur'an"),
    'address' => env('RAPAT_ADDRESS', 'Pakel, Andong, Boyolali, Jawa Tengah'),
    'city' => env('RAPAT_CITY', 'Boyolali'),
    'tagline' => env('RAPAT_TAGLINE', 'Mulia dan Memuliakan dengan Al-Qur\'an'),

    // Peserta dianggap terlambat bila presensi lebih dari N menit setelah jam mulai
    'late_after_minutes' => (int) env('RAPAT_LATE_AFTER', 15),

    // Agenda pembuka & penutup yang otomatis ditambahkan pada rapat baru
    'opening_agendas' => ['Pembukaan, tilawah & doa'],
    'carry_over_agenda' => 'Evaluasi RTL rapat sebelumnya',
    'closing_agendas' => ['Lain-lain', 'Penutup & doa kafaratul majelis'],

    // Lampiran agenda & bukti RTL (disimpan privat di storage/app/private/lampiran)
    'attachments' => [
        'disk' => env('RAPAT_ATTACHMENT_DISK', 'local'),
        'max_kb' => (int) env('RAPAT_ATTACHMENT_MAX_KB', 10240),          // 10 MB per file
        'max_files' => 10,                                               // per sekali unggah
        'mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp'],
        // Foto diperkecil otomatis: sisi terpanjang & kualitas JPEG/WEBP
        'image_max_side' => 1920,
        'image_quality' => 82,
    ],
];
