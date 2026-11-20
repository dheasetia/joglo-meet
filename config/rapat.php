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
];
