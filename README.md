# Aplikasi Manajemen Rapat — MVP

Aplikasi pencatatan rapat untuk Pondok Pesantren Joglo Qur'an. Isinya agenda, pembahasan, notulen, keputusan, presensi peserta, serta rencana tindak lanjut (RTL) dengan penanggung jawab dan deadline.

**Stack:** Laravel 13 · Livewire 4 (halaman interaktif tanpa reload) · Filament 5 (data master di `/admin`) · Tailwind CSS 4 · Vite 8 · MySQL.

## 1. Instalasi

Butuh PHP 8.3+, Composer, Node.js 20+, dan MySQL/MariaDB.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run build
php artisan filament:assets
```

Font (Plus Jakarta Sans) di-host sendiri lewat plugin font Vite (`@fonts` di layout), tidak memakai CDN.

## 2. Konfigurasi `.env`

```env
APP_NAME="Manajemen Rapat"
APP_LOCALE=id
APP_FAKER_LOCALE=id_ID

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rapat_ppjq
DB_USERNAME=root
DB_PASSWORD=

# Opsional — kop cetak notulen
RAPAT_INSTITUTION="Pondok Pesantren Joglo Qur'an"
RAPAT_ADDRESS="Pakel, Andong, Boyolali, Jawa Tengah"
RAPAT_CITY=Boyolali
RAPAT_LATE_AFTER=15
```

Zona waktu diatur lewat `APP_TIMEZONE` (bawaan `Asia/Jakarta`).

## 3. Database & menjalankan aplikasi

```bash
php artisan migrate --seed
php artisan serve        # atau: composer run dev
```

Buka `http://127.0.0.1:8000`.

### Akun contoh (kata sandi semua: `password`)

| Email | Peran |
|---|---|
| admin@jogloquran.com | Super Admin |
| pimpinan@jogloquran.com | Pimpinan (Mudir) |
| fauzan@jogloquran.com | Notulis / Sekretaris (SMA) |
| irfansyah@jogloquran.com | Notulis / Sekretaris (SMP) |
| irfan@jogloquran.com | Kepala Unit SMA |
| azzam@jogloquran.com | Kepala Unit SMP |
| pukat@jogloquran.com | Koordinator Tahfizh |
| sigit@jogloquran.com | Kepala Pengasuhan |
| ustadz1@jogloquran.com … ustadz6@jogloquran.com | Peserta |

**Segera ganti kata sandi** sebelum dipakai sungguhan. Kata sandi bisa diganti di `/admin` → Pengguna.

## 4. Uji otomatis

```bash
php artisan test
```

`MeetingFlowTest` membuka semua halaman, lalu menjalankan alur lengkap: membuat rapat, presensi, pembahasan, keputusan, RTL, update progres oleh PIC, carry-over ke rapat berikutnya, dan pengesahan. `InteractionSmokeTest` menjalankan tombol, filter, dan modal di setiap halaman Livewire serta presensi QR. Tes memakai SQLite in-memory; untuk menguji di MySQL jalankan `DB_CONNECTION=mysql DB_DATABASE=<db_uji> php artisan test`.

---

## 5. Fitur MVP

| Menu | Isi |
|---|---|
| **Dashboard** | Rapat bulan ini, rata-rata kehadiran, RTL aktif & terlambat, deadline 7 hari, rapat mendatang, notulen menunggu pengesahan |
| **Rapat** | Daftar & pencarian (judul, pembahasan, keputusan), filter jenis/status/tanggal |
| **Rapat Baru / Ubah** | Data rapat, peserta per unit (pilih semua per unit), agenda dari template jenis rapat, urutan naik-turun, alokasi waktu |
| **Ruang Rapat** | Tab *Notulen*: pembahasan per agenda tersimpan otomatis, keputusan, RTL per keputusan. Tab *Presensi*: hadir/terlambat/izin/sakit/alpa, tamu, QR presensi mandiri. Tab *RTL*: rekap. Tombol Mulai, Akhiri, Sahkan, dan Cetak |
| **Evaluasi RTL** | Agenda "Evaluasi RTL rapat sebelumnya" otomatis menampilkan RTL yang belum selesai dari rapat sejenis sebelumnya. Progres dapat dicatat langsung di forum |
| **Papan RTL** | Tampilan tabel & kanban, filter PIC/unit/jenis, perbarui progres + riwayat |
| **Tugas Saya** | Daftar RTL milik pengguna (nyaman di HP), perbarui progres & catatan |
| **Laporan** | Rekap kehadiran per orang per periode (pimpinan/admin) |
| **Cetak Notulen** | Berkop lembaga, agenda, pembahasan, keputusan, tabel RTL, daftar hadir, kolom pengesahan. Simpan PDF lewat dialog cetak browser |
| **/admin** | Data master: pengguna & peran, unit, jenis rapat + template agenda |

### Alur status rapat

`Terjadwal → Berlangsung → Menunggu Pengesahan → Disahkan (terkunci)`

- Saat rapat diakhiri, peserta yang belum presensi otomatis ditandai **alpa**.
- Notulen yang sudah disahkan terkunci. Hanya Super Admin/Pimpinan yang bisa membuka kuncinya kembali.

### Hak akses

- **Super Admin & Pimpinan**: melihat dan mengelola semua rapat, membuka `/admin` dan Laporan.
- **Notulis & Kepala Unit**: dapat membuat rapat.
- **Pimpinan rapat, notulis, dan pembuat rapat**: dapat mengelola rapat tersebut.
- **Peserta**: hanya melihat rapat yang ia ikuti dan memperbarui RTL miliknya.
- Rapat **rahasia** tidak bisa dibuka oleh orang di luar peserta.
- Pengesahan dilakukan oleh pimpinan rapat atau Pimpinan/Super Admin.

## 6. Struktur kode

```
app/Enums/                 Status rapat, kehadiran, RTL, prioritas, peran
app/Models/                Meeting, Agenda, Decision, ActionItem, ActionItemUpdate, MeetingParticipant, ...
app/Livewire/              Dashboard, MeetingIndex, MeetingForm, MeetingRoom, ActionBoard, MyTasks, AttendanceReport
app/Filament/Resources/    Users, Units, MeetingTypes
app/Http/Controllers/      Login, presensi QR, cetak notulen
config/rapat.php           Kop surat, batas terlambat, agenda pembuka/penutup otomatis
resources/views/           Layout, halaman Livewire, cetak notulen
```

## 7. Tahap kedua

| Fitur | Status |
|---|---|
| **Log aktivitas**: siapa mengubah apa & kapan (rapat, notulen, keputusan, RTL, presensi, data master, login). Tab *Riwayat* di Ruang Rapat (pengelola rapat) dan menu *Log Aktivitas* di `/admin`. Log > 365 hari dihapus otomatis. | ✅ Selesai |
| Lampiran berkas/foto pada agenda dan bukti RTL | ⏳ |
| Ekspor Excel (RTL, rekap kehadiran) | ⏳ |
| Tanda tangan digital QR pada notulen yang disahkan | ⏳ |
| Rapat berulang otomatis | ⏳ |
| Notifikasi WhatsApp (Fonnte): undangan, pengingat H-1, deadline RTL | ⏳ |

Belum direncanakan: presensi RFID.

## 8. Penjadwal (cron)

Tugas terjadwal (pembersihan log, dan nanti rapat berulang & notifikasi WhatsApp) dijalankan oleh penjadwal Laravel. Di shared hosting (cPanel → *Cron Jobs*) cukup tambahkan **satu** cron tiap menit:

```
* * * * * cd /home/USER/path-aplikasi && php artisan schedule:run >> /dev/null 2>&1
```

Daftar tugas terjadwal: `php artisan schedule:list`.
