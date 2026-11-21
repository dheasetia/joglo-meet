# Panduan Deploy di Shared Hosting (cPanel)

Panduan memasang **Manajemen Rapat — Pondok Pesantren Joglo Qur'an** di shared hosting berbasis cPanel, dari nol sampai notifikasi WhatsApp berjalan, beserta cara memperbarui dan mencadangkan.

> **Ringkasnya:** siapkan paket rilis di komputer → buat database → unggah aplikasi ke luar `public_html` → arahkan domain ke folder `public` → isi `.env` → jalankan migrasi & buat admin → pasang **satu** cron tiap menit.

---

## Daftar isi

1. [Kebutuhan hosting](#1-kebutuhan-hosting)
2. [Siapkan paket rilis di komputer](#2-siapkan-paket-rilis-di-komputer)
3. [Atur versi & setelan PHP](#3-atur-versi--setelan-php)
4. [Buat database MySQL](#4-buat-database-mysql)
5. [Unggah aplikasi](#5-unggah-aplikasi)
6. [Arahkan domain ke folder `public`](#6-arahkan-domain-ke-folder-public)
7. [Isi berkas `.env`](#7-isi-berkas-env)
8. [Perintah pemasangan pertama](#8-perintah-pemasangan-pertama)
9. [Pasang cron](#9-pasang-cron)
10. [HTTPS](#10-https)
11. [Daftar periksa setelah deploy](#11-daftar-periksa-setelah-deploy)
12. [Memperbarui aplikasi](#12-memperbarui-aplikasi)
13. [Cadangan (backup)](#13-cadangan-backup)
14. [Pemecahan masalah](#14-pemecahan-masalah)

Di seluruh panduan, ganti:
- `USER` → nama pengguna cPanel Anda (lihat pojok kanan atas cPanel),
- `rapat.jogloquran.com` → domain/subdomain aplikasi,
- `joglo-meet` → nama folder aplikasi di hosting (boleh diganti, asal konsisten).

---

## 1. Kebutuhan hosting

| Kebutuhan | Keterangan |
|---|---|
| **PHP 8.4** atau lebih baru | Wajib. PHP 8.3 tidak cukup (dipakai `spatie/laravel-activitylog` v5). |
| Ekstensi PHP | `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `iconv`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `xmlreader`, **`zip`** (ekspor Excel), **`intl`** (panel /admin). Disarankan: **`gd`** dan **`exif`** (memperkecil & memutar foto lampiran; tanpa ini foto disimpan apa adanya). |
| Database | MySQL 8.0+ atau MariaDB 10.6+ |
| **Cron job** | Wajib — satu cron tiap menit (antrean WhatsApp, pengingat, jadwal rutin). |
| Terminal / SSH | **Sangat disarankan** (cPanel → *Terminal*). Tanpa ini tetap bisa, lihat [8b](#8b-tanpa-terminal-lewat-cron-sekali-jalan). |
| SSL (HTTPS) | Wajib untuk produksi; cPanel biasanya menyediakan *AutoSSL* gratis. |
| Ruang disk | ± 150 MB untuk aplikasi + ruang lampiran (maks. 10 MB per berkas). Berkas sementara ekspor Excel ditulis ke `storage/app/tmp` (bukan `/tmp`), jadi aman dengan pembatasan `open_basedir`. |

Node.js **tidak** dibutuhkan di hosting — aset CSS/JS dibangun di komputer (langkah 2).

---

## 2. Siapkan paket rilis di komputer

Di komputer pengembang (yang sudah punya PHP 8.4, Composer, Node.js, dan salinan repo):

```bash
cd joglo-meet
git pull                               # pastikan kode terbaru
bash scripts/buat-paket-rilis.sh       # hasil: ../joglo-meet-rilis-YYYYMMDD-HHMM-<commit>.zip
```

Skrip ini:
- hanya mengambil **berkas yang sudah di-commit** (`.env`, `node_modules`, berkas lokal tidak ikut),
- menjalankan `npm run build` dan menyertakan `public/build`,
- memasang `vendor/` **produksi** (`composer install --no-dev --optimize-autoloader`),
- membuang folder `tests`.

Hasilnya satu berkas `.zip` (± 20–25 MB) siap diunggah.

> Bila hosting punya **Terminal + Git + Composer**, Anda bisa memakai cara [5b](#5b-lewat-terminal--git) — tapi aset `public/build` tetap dibangun di komputer karena hosting umumnya tidak punya Node.js.

---

## 3. Atur versi & setelan PHP

1. cPanel → **MultiPHP Manager** (atau **Select PHP Version** di hosting CloudLinux) → pilih domain aplikasi → **PHP 8.4**.
2. Pastikan ekstensi di [tabel kebutuhan](#1-kebutuhan-hosting) tercentang (di *Select PHP Version* → *Extensions*).
3. cPanel → **MultiPHP INI Editor** → pilih domain:

   | Setelan | Nilai minimal |
   |---|---|
   | `upload_max_filesize` | `12M` |
   | `post_max_size` | `14M` |
   | `memory_limit` | `256M` (minimal `128M`) |
   | `max_execution_time` | `60` |

   Aplikasi sudah menyertakan `public/.user.ini` berisi batas unggah di atas; sebagian hosting membacanya otomatis, tapi mengatur lewat MultiPHP INI Editor lebih pasti.

---

## 4. Buat database MySQL

cPanel → **MySQL® Database Wizard**:

1. Nama database, mis. `USER_rapat`.
2. Pengguna database, mis. `USER_rapat`, dengan **kata sandi kuat** (catat).
3. Hak akses: **ALL PRIVILEGES**.

Catat tiga nilai ini untuk `.env`: nama database, nama pengguna, kata sandi. Host-nya hampir selalu `localhost`.

---

## 5. Unggah aplikasi

Aplikasi diletakkan **di luar** `public_html`, mis. `/home/USER/joglo-meet`, agar berkas `.env`, kode, dan lampiran tidak bisa dibuka dari web. Hanya isi folder `public` yang terlihat dari internet.

### 5a. Lewat File Manager (tanpa SSH)

1. cPanel → **File Manager** → buka folder *home* (`/home/USER`).
2. Buat folder `joglo-meet` → masuk ke dalamnya.
3. **Upload** berkas `joglo-meet-rilis-….zip`.
4. Klik kanan zip → **Extract** ke `/home/USER/joglo-meet` → hapus zip-nya.
5. Pastikan di dalam `/home/USER/joglo-meet` ada `artisan`, `app/`, `public/`, `vendor/`, `storage/`.

### 5b. Lewat Terminal + Git

```bash
cd ~
git clone https://github.com/dheasetia/joglo-meet.git joglo-meet
cd joglo-meet
composer install --no-dev --optimize-autoloader
```

Lalu unggah folder **`public/build`** dari komputer (hasil `npm run build`) ke `~/joglo-meet/public/build` lewat File Manager, karena folder ini tidak disimpan di Git.

### Izin folder

Folder `storage` dan `bootstrap/cache` harus bisa ditulis PHP. Di cPanel biasanya sudah benar (folder `755`, berkas `644`). Bila muncul galat *Permission denied*:

```bash
cd ~/joglo-meet
find storage bootstrap/cache -type d -exec chmod 755 {} \;
find storage bootstrap/cache -type f -exec chmod 644 {} \;
```

---

## 6. Arahkan domain ke folder `public`

Pilih **satu** cara.

### Cara A — Subdomain/addon domain (disarankan)

cPanel → **Domains** → *Create A New Domain* (atau ubah domain yang ada):
- Domain: `rapat.jogloquran.com`
- **Document Root**: `joglo-meet/public` (= `/home/USER/joglo-meet/public`)
- Hilangkan centang *Share document root* bila ada.

Selesai — tidak ada berkas yang perlu dipindah.

### Cara B — Domain utama memakai `public_html` (dengan symlink, butuh Terminal)

```bash
cd ~
mv public_html public_html_lama        # cadangkan isi lama
ln -s ~/joglo-meet/public public_html
```

Sebagian hosting tidak mengizinkan `public_html` berupa symlink; bila situs jadi galat 403/500, kembalikan (`rm public_html && mv public_html_lama public_html`) dan pakai Cara A atau C.

### Cara C — Domain utama tanpa symlink (salin isi `public`)

1. Salin **seluruh isi** `~/joglo-meet/public/` (termasuk berkas tersembunyi `.htaccess` dan `.user.ini`) ke `~/public_html/`.
2. Ubah `~/public_html/index.php` menjadi:

   ```php
   <?php

   use Illuminate\Foundation\Application;
   use Illuminate\Http\Request;

   define('LARAVEL_START', microtime(true));

   $app_path = __DIR__.'/../joglo-meet';

   if (file_exists($maintenance = $app_path.'/storage/framework/maintenance.php')) {
       require $maintenance;
   }

   require $app_path.'/vendor/autoload.php';

   /** @var Application $app */
   $app = require_once $app_path.'/bootstrap/app.php';

   // Aset (public/build, gambar, dll.) ada di public_html, bukan di joglo-meet/public
   $app->usePublicPath(__DIR__);

   $app->handleRequest(Request::capture());
   ```

3. **Setiap memperbarui aplikasi**, salin ulang isi `public/` (terutama `build/`) ke `public_html` — tanpa menimpa `index.php` yang sudah diubah.

> Cara C paling rawan lupa saat pembaruan. Bila memungkinkan, pakai Cara A.

---

## 7. Isi berkas `.env`

Di `~/joglo-meet`, salin `.env.example` menjadi `.env` (File Manager: klik kanan → *Copy*; aktifkan *Show Hidden Files* lewat *Settings* agar berkas berawalan titik terlihat), lalu **Edit**:

```env
APP_NAME="Manajemen Rapat"
APP_ENV=production
APP_KEY=                       # diisi otomatis pada langkah 8
APP_DEBUG=false
APP_URL=https://rapat.jogloquran.com

APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=USER_rapat
DB_USERNAME=USER_rapat
DB_PASSWORD="kata-sandi-database"

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_SECURE_COOKIE=true     # karena memakai HTTPS

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

# Kop cetak notulen
RAPAT_INSTITUTION="Pondok Pesantren Joglo Qur'an"
RAPAT_ADDRESS="Pakel, Andong, Boyolali, Jawa Tengah"
RAPAT_CITY=Boyolali
RAPAT_LATE_AFTER=15

# Notifikasi WhatsApp (Fonnte). Mulai dengan "log" (hanya dicatat), ganti "fonnte" setelah token siap.
WHATSAPP_DRIVER=log
FONNTE_TOKEN=
WHATSAPP_REMINDER_TIME=07:00
```

Penting:
- **`APP_URL`** harus persis domain aplikasi (dengan `https://`). Alamat ini dipakai pada tautan pesan WhatsApp yang dikirim oleh cron dan pada QR verifikasi.
- **`APP_DEBUG=false`** di produksi — bila `true`, pesan galat dapat membocorkan konfigurasi.
- Kata sandi berisi spasi atau `#` wajib diapit tanda kutip.

---

## 8. Perintah pemasangan pertama

### 8a. Dengan Terminal (cPanel → *Terminal*, atau SSH)

Pastikan perintah `php` memakai PHP 8.4:

```bash
php -v
```

Bila yang muncul bukan 8.4, pakai path lengkap (lihat [langkah 9](#cari-path-php-84)), mis. `/opt/cpanel/ea-php84/root/usr/bin/php artisan …`.

```bash
cd ~/joglo-meet

php artisan key:generate --force                 # mengisi APP_KEY
php artisan migrate --force                      # membuat semua tabel
php artisan rapat:buat-admin admin@jogloquran.com --nama="Administrator"
#   → mencetak kata sandi acak; SIMPAN, lalu ganti setelah masuk
php artisan optimize                             # cache config, route, view, event
```

**Jangan** menjalankan `php artisan db:seed` di produksi — seeder berisi data contoh dengan kata sandi `password`.

### 8b. Tanpa Terminal (lewat cron sekali jalan)

Perintah artisan tetap bisa dijalankan memakai cron:

1. cPanel → **Cron Jobs** → *Add New Cron Job* → *Common Settings*: **Once Per Minute**.
2. Command (satu baris; ganti path PHP & email):

   ```
   cd /home/USER/joglo-meet && { P=/opt/cpanel/ea-php84/root/usr/bin/php; $P artisan key:generate --force && $P artisan migrate --force && $P artisan rapat:buat-admin admin@jogloquran.com --password="KataSandiSementara123" && $P artisan optimize; } >> storage/logs/pasang.log 2>&1
   ```

3. Tunggu 1–2 menit, buka `storage/logs/pasang.log` di File Manager untuk memastikan semua berhasil.
4. **Hapus cron ini** (agar tidak berjalan terus), lalu pasang cron tetap pada langkah 9.
5. Masuk dengan kata sandi sementara tadi dan **segera ganti** di `/admin` → *Pengguna*.

---

## 9. Pasang cron

Aplikasi butuh **satu** cron tiap menit. Cron ini menjalankan seluruh tugas terjadwal:

| Waktu | Tugas |
|---|---|
| tiap menit | memproses antrean pesan WhatsApp |
| 05.00 | membuat rapat dari jadwal rutin (+ undangan WA) |
| 07.00 (`WHATSAPP_REMINDER_TIME`) | pengingat rapat besok, deadline RTL besok, RTL terlambat |
| 02.00 | menghapus log aktivitas > 365 hari |

### Cari path PHP 8.4

Cron sering memakai PHP bawaan server (bisa versi lama), jadi tulis **path lengkap** PHP 8.4. Umumnya salah satu dari:

- cPanel EasyApache: `/opt/cpanel/ea-php84/root/usr/bin/php`
- CloudLinux *Select PHP Version*: `/opt/alt/php84/usr/bin/php`

Cek di Terminal: `ls /opt/cpanel/ea-php84/root/usr/bin/php /opt/alt/php84/usr/bin/php`. Bila ragu, tanyakan ke penyedia hosting “path PHP 8.4 CLI untuk cron”.

### Tambahkan cron

cPanel → **Cron Jobs** → *Add New Cron Job* → **Once Per Minute** (`* * * * *`):

```
cd /home/USER/joglo-meet && /opt/cpanel/ea-php84/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Periksa daftar tugas (Terminal): `php artisan schedule:list`.

> Jangan menambah cron terpisah untuk `queue:work` — sudah dijalankan oleh `schedule:run` (berhenti sendiri bila antrean kosong, cocok untuk shared hosting).

---

## 10. HTTPS

1. cPanel → **SSL/TLS Status** → jalankan **AutoSSL** untuk domain aplikasi (atau pasang sertifikat dari hosting).
2. Paksa HTTPS: tambahkan baris berikut di `public/.htaccess` (Cara C: di `public_html/.htaccess`) **tepat setelah** `RewriteEngine On`:

   ```apache
   RewriteCond %{HTTPS} off
   RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

   (Sebagian cPanel punya tombol *Force HTTPS Redirect* di menu **Domains** — cukup pakai itu.)
3. Pastikan `.env`: `APP_URL=https://…` dan `SESSION_SECURE_COOKIE=true`, lalu `php artisan optimize`.

---

## 11. Daftar periksa setelah deploy

- [ ] `https://rapat.jogloquran.com` menampilkan halaman **Masuk** berlogo pesantren (tanpa galat).
- [ ] Masuk dengan akun admin dari langkah 8 → **ganti kata sandi** di `/admin` → *Pengguna*.
- [ ] `/admin` → isi **Unit / Divisi**, **Jenis Rapat** (+ template agenda), dan **Pengguna** (peran & **No. WhatsApp** format `08…`).
- [ ] Buat satu rapat uji → unggah satu foto lampiran (cek ukuran tidak ditolak) → **Cetak / PDF** & **Excel** terbuka.
- [ ] Sahkan rapat uji → pindai QR di cetak notulen dengan HP → halaman verifikasi menyatakan **SAH** dengan domain yang benar.
- [ ] `/admin` → **Pesan WhatsApp** → *Kirim pesan uji* (mode `log`) → status menjadi *Terkirim* dalam 1–2 menit ⇒ **cron berjalan**.
- [ ] WhatsApp sungguhan: daftar & sambungkan perangkat di [fonnte.com](https://fonnte.com), isi `.env` `WHATSAPP_DRIVER=fonnte` dan `FONNTE_TOKEN=…`, jalankan `php artisan optimize`, kirim pesan uji ke nomor sendiri.
- [ ] Hapus rapat/pengguna uji bila tidak diperlukan.

---

## 12. Memperbarui aplikasi

1. **Cadangkan dulu** (langkah 13).
2. Aktifkan mode perawatan (Terminal): `php artisan down --render="errors::503"`.
3. Ganti kode:
   - **File Manager:** buat paket rilis baru (langkah 2), unggah & ekstrak ke folder sementara, lalu timpa isi `~/joglo-meet` **kecuali** `.env` dan `storage/` (berisi lampiran, log, sesi).
   - **Git:** `git pull && composer install --no-dev --optimize-autoloader`, lalu unggah ulang `public/build`.
   - **Cara C (public_html):** salin ulang isi `public/` ke `public_html` tanpa menimpa `index.php`.
4. Jalankan:

   ```bash
   cd ~/joglo-meet
   php artisan migrate --force
   php artisan optimize:clear
   php artisan optimize
   php artisan up
   ```

   Tanpa Terminal: pakai cron sekali jalan seperti [8b](#8b-tanpa-terminal-lewat-cron-sekali-jalan), lalu hapus cron-nya:

   ```
   cd /home/USER/joglo-meet && { P=/opt/cpanel/ea-php84/root/usr/bin/php; $P artisan migrate --force && $P artisan optimize:clear && $P artisan optimize && $P artisan up; } >> storage/logs/pasang.log 2>&1
   ```

   (Langkah 2 *mode perawatan* tanpa Terminal bisa dilewati, atau tambahkan `$P artisan down &&` di awal cron terpisah.)

> Setelah mengubah `.env`, selalu jalankan `php artisan optimize` lagi — konfigurasi dibaca dari cache.

---

## 13. Cadangan (backup)

Cadangkan rutin (mis. sepekan sekali) dan **sebelum setiap pembaruan**:

| Apa | Di mana | Cara |
|---|---|---|
| Database | MySQL `USER_rapat` | cPanel → **Backup** → *Download a MySQL Database Backup*, atau phpMyAdmin → *Export* |
| Lampiran | `~/joglo-meet/storage/app/private/lampiran/` | File Manager → *Compress* → unduh |
| Konfigurasi | `~/joglo-meet/.env` | unduh (berisi kata sandi — simpan aman) |

Kode aplikasi tidak perlu dicadangkan karena tersimpan di GitHub.

---

## 14. Pemecahan masalah

Langkah pertama untuk semua galat: buka **`~/joglo-meet/storage/logs/laravel.log`** (baris paling bawah).

| Gejala | Penyebab & solusi |
|---|---|
| **500 Server Error** tanpa keterangan | Lihat `laravel.log`. Umumnya: `.env` belum ada / `APP_KEY` kosong (jalankan `key:generate`), izin folder `storage` (langkah 5), atau versi PHP < 8.4. |
| *“Your Composer dependencies require a PHP version ">= 8.4.0"”* | Domain belum memakai PHP 8.4 (langkah 3), atau cron/Terminal memakai PHP lain — pakai path lengkap PHP 8.4. |
| *“Vite manifest not found”* | Folder `public/build` belum diunggah (langkah 2/5b). Cara C: salin juga ke `public_html/build`. |
| Halaman tampil tanpa gaya (polos) | Sama seperti di atas, atau `APP_URL` berbeda domain dengan alamat yang dibuka. |
| **404** di semua halaman kecuali beranda | `.htaccess` di folder public tidak ikut tersalin (berkas tersembunyi) atau `mod_rewrite` nonaktif. |
| *“419 Page Expired”* saat masuk | `SESSION_SECURE_COOKIE=true` tapi situs dibuka lewat `http://` — aktifkan HTTPS (langkah 10). Lalu `php artisan optimize`. |
| Unggah lampiran: *“Lampiran gagal diunggah… melebihi batas server”* | `upload_max_filesize`/`post_max_size` kurang dari 12M (langkah 3). |
| Pesan WhatsApp tetap **Antre** | Cron tidak berjalan atau path PHP salah. Cek *Cron Jobs*, jalankan manual `php artisan schedule:run`, lihat `php artisan schedule:list`. |
| Pesan WhatsApp **Gagal** | Lihat kolom status di `/admin` → *Pesan WhatsApp*: `FONNTE_TOKEN belum diisi`, `Fonnte: token invalid` (token salah/perangkat terputus di fonnte.com), `target invalid` (nomor pengguna salah). Perbaiki lalu **Kirim ulang**. |
| Pesan WhatsApp **Dilewati** | Pengguna belum punya No. WhatsApp valid — isi di `/admin` → *Pengguna*, lalu **Kirim ulang**. |
| Tautan di pesan WA / QR mengarah ke domain lain | `APP_URL` salah → perbaiki `.env`, `php artisan optimize`. Untuk notulen lama, QR mengikuti domain yang sedang dibuka saat dicetak. |
| Rapat dari jadwal rutin tidak muncul | Cron belum berjalan pukul 05.00, jadwal *Nonaktif*, atau di luar masa berlaku. Bisa dipicu manual: `php artisan rapat:buat-dari-jadwal`. |
| Notulen lama (disahkan sebelum fitur QR) tanpa QR | Jalankan sekali: `php artisan rapat:terbitkan-tanda-tangan`. |
| Lupa kata sandi admin | `php artisan rapat:buat-admin email-admin@…` → kata sandi baru dicetak. |
| Jam/tanggal selisih 7 jam | `APP_TIMEZONE=Asia/Jakarta` di `.env`, lalu `php artisan optimize`. |
| Setelah ubah `.env` tidak berpengaruh | Konfigurasi ter-cache: `php artisan optimize:clear && php artisan optimize`. |

---

### Perintah artisan khusus aplikasi

| Perintah | Kegunaan |
|---|---|
| `php artisan rapat:buat-admin EMAIL [--nama=…] [--password=…]` | Buat Super Admin / setel ulang kata sandinya |
| `php artisan rapat:buat-dari-jadwal` | Buat rapat dari jadwal rutin (otomatis 05.00) |
| `php artisan rapat:kirim-pengingat` | Antrekan pengingat WhatsApp (otomatis 07.00) |
| `php artisan rapat:terbitkan-tanda-tangan [--dry-run]` | Terbitkan kode verifikasi QR untuk notulen lama |
| `php artisan activitylog:clean --force` | Hapus log aktivitas > 365 hari (otomatis 02.00) |
