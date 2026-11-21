<?php

namespace Database\Seeders;

use App\Enums\ActionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\MeetingStatus;
use App\Enums\Role;
use App\Models\Meeting;
use App\Models\MeetingSignature;
use App\Models\MeetingType;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Data contoh tidak perlu masuk log aktivitas
        activity()->withoutLogging(fn () => $this->seedData());
    }

    protected function seedData(): void
    {
        // ---------- Unit ----------
        $units = collect(['Pimpinan', 'SMP', 'SMA', 'Pengasuhan', 'Tahfizh', 'Yayasan'])
            ->mapWithKeys(fn ($name) => [$name => Unit::create(['name' => $name])]);

        // ---------- Pengguna (password semua: password) ----------
        $make = fn (string $name, string $email, Role $role, string $unit, ?string $jabatan = null) => User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'unit_id' => $units[$unit]->id,
            'jabatan' => $jabatan,
            'is_active' => true,
        ]);

        $admin = $make('Administrator', 'admin@jogloquran.com', Role::SuperAdmin, 'Pimpinan', 'Admin Sistem');
        $mudir = $make('Pimpinan Pondok', 'pimpinan@jogloquran.com', Role::Pimpinan, 'Pimpinan', 'Mudir');
        $notulisSMA = $make('Fauzan Ryan Abdullah', 'fauzan@jogloquran.com', Role::Notulis, 'Pimpinan', 'Sekretaris Pondok');
        $notulisSMP = $make('Irfansyah Ihsan', 'irfansyah@jogloquran.com', Role::Notulis, 'Pimpinan', 'Sekretaris Pondok');
        $kepSma = $make('Ust. Irfan Fachrurrazi', 'irfan@jogloquran.com', Role::KepalaUnit, 'SMA', 'Kepala SMA');
        $kepSmp = $make('Ust. Muhammad Azzam', 'azzam@jogloquran.com', Role::KepalaUnit, 'SMP', 'Kepala SMP');
        $tahfizh = $make('Ust. Pukat Amigura', 'pukat@jogloquran.com', Role::KepalaUnit, 'Tahfizh', 'Koordinator Tahfizh');
        $pengasuhan = $make('Ust. Sigit Trihatmojo', 'sigit@jogloquran.com', Role::KepalaUnit, 'Pengasuhan', 'Kepala Pengasuhan');

        $asatidz = collect([
            ['Ust. Rasyid Ridha', 'SMA', 'Guru Fiqih'],
            ['Ust. Hasan Amin', 'SMA', 'Guru Bahasa Arab'],
            ['Ust. Fathurrahman', 'SMA', 'Guru Matematika'],
            ['Usth. Ali', 'SMA', 'Guru Bahasa Inggris'],
            ['Ust. Irfansyah', 'Tahfizh', 'Muhaffizh'],
            ['Ust. Ashim', 'Pengasuhan', 'Musyrif'],
        ])->map(fn ($a, $i) => $make($a[0], 'ustadz'.($i + 1).'@jogloquran.com', Role::Peserta, $a[1], $a[2]));

        // ---------- Jenis rapat ----------
        $rapim = MeetingType::create([
            'name' => 'Rapat Pimpinan',
            'unit_id' => $units['Pimpinan']->id,
            'default_agendas' => ['Laporan kepala unit', 'Pembahasan program pekan depan'],
        ]);
        $asatidzSMAType = MeetingType::create([
            'name' => 'Rapat Asatidz SMA Mingguan',
            'unit_id' => $units['SMA']->id,
            'default_agendas' => ['Evaluasi KBM pekan ini', 'Perkembangan santri', 'Agenda pekan depan'],
        ]);
        $asatidzSMPType = MeetingType::create([
            'name' => 'Rapat Asatidz SMP Mingguan',
            'unit_id' => $units['SMP']->id,
            'default_agendas' => ['Evaluasi KBM pekan ini', 'Perkembangan santri', 'Agenda pekan depan'],
        ]);

        MeetingType::create([
            'name' => 'Rapat Pengasuhan / Musyrif',
            'unit_id' => $units['Pengasuhan']->id,
            'default_agendas' => ['Evaluasi kedisiplinan santri', 'Kasus santri yang perlu penanganan'],
        ]);
        MeetingType::create(['name' => 'Rapat Ketahfizhan', 'unit_id' => $units['SMP']->id]);
        MeetingType::create(['name' => 'Rapat Yayasan', 'unit_id' => $units['Yayasan']->id]);

        // ---------- Contoh rapat yang sudah disahkan ----------
        $past = Meeting::create([
            'meeting_type_id' => $asatidzSMAType->id,
            'unit_id' => $units['SMA']->id,
            'title' => 'Rapat Asatidz SMA Pekan II',
            'date' => today()->subDays(7),
            'start_time' => '13:00',
            'end_time' => '14:30',
            'location' => 'Ruang Guru SMA',
            'chair_id' => $mudir->id,
            'secretary_id' => $notulisSMA->id,
            'status' => MeetingStatus::Disahkan,
            'started_at' => today()->subDays(7)->setTime(13, 5),
            'ended_at' => today()->subDays(7)->setTime(14, 35),
            'approved_at' => today()->subDays(6)->setTime(8, 0),
            'approved_by' => $kepSma->id,
            'created_by' => $notulisSMA->id,
            'notes' => 'Rapat ditutup dengan doa kafaratul majelis.',
        ]);

        $pastSmp = Meeting::create([
            'meeting_type_id' => $asatidzSMPType->id,
            'unit_id' => $units['SMP']->id,
            'title' => 'Rapat Asatidz SMP Pekan II',
            'date' => today()->subDays(7),
            'start_time' => '13:00',
            'end_time' => '14:30',
            'location' => 'sekretariat',
            'chair_id' => $mudir->id,
            'secretary_id' => $notulisSMP->id,
            'status' => MeetingStatus::Disahkan,
            'started_at' => today()->subDays(7)->setTime(13, 5),
            'ended_at' => today()->subDays(7)->setTime(14, 35),
            'approved_at' => today()->subDays(6)->setTime(8, 0),
            'approved_by' => $kepSmp->id,
            'created_by' => $notulisSMP->id,
            'notes' => 'Rapat ditutup dengan doa kafaratul majelis.',
        ]);

        // Peserta & agenda rapat SMP (tanpa keputusan/RTL contoh)
        foreach ([$mudir, $kepSmp, $notulisSMP] as $user) {
            $pastSmp->participants()->create([
                'user_id' => $user->id,
                'attendance_status' => AttendanceStatus::Hadir,
                'checked_in_at' => $pastSmp->started_at,
            ]);
        }
        foreach (['Pembukaan, tilawah & doa', 'Evaluasi RTL rapat sebelumnya', 'Evaluasi KBM pekan ini', 'Perkembangan santri', 'Agenda pekan depan', 'Lain-lain', 'Penutup & doa kafaratul majelis'] as $i => $title) {
            $pastSmp->agendas()->create(['sort_order' => $i + 1, 'title' => $title, 'is_carry_over' => $i === 1]);
        }

        // ---------- Detail rapat SMA Pekan II: peserta, agenda, keputusan & RTL ----------
        $people = collect([$kepSma, $notulisSMP, $notulisSMA, $tahfizh])->merge($asatidz->take(4));
        $statuses = [AttendanceStatus::Hadir, AttendanceStatus::Hadir, AttendanceStatus::Hadir, AttendanceStatus::Hadir, AttendanceStatus::Hadir,
            AttendanceStatus::Terlambat, AttendanceStatus::Izin, AttendanceStatus::Hadir];
        foreach ($people->values() as $i => $user) {
            $past->participants()->create([
                'user_id' => $user->id,
                'attendance_status' => $statuses[$i] ?? AttendanceStatus::Hadir,
                'checked_in_at' => $statuses[$i]?->isPresent() ? $past->started_at : null,
                'note' => ($statuses[$i] ?? null) === AttendanceStatus::Izin ? 'Mengantar santri berobat' : null,
            ]);
        }

        $agendaTitles = [
            ['Pembukaan, tilawah & doa', null],
            ['Evaluasi RTL rapat sebelumnya', null],
            ['Evaluasi KBM pekan ini', 'Banyak santri kelas XI terlambat masuk kelas jam pertama karena antre kamar mandi. Nilai harian Bahasa Arab kelas X menurun.'],
            ['Perkembangan santri', 'Tiga santri belum mencapai target setoran hafalan bulan ini. Perlu koordinasi dengan bagian tahfizh.'],
            ['Agenda pekan depan', 'Persiapan Penilaian Tengah Semester (PTS) dan pembagian pengawas.'],
            ['Lain-lain', null],
            ['Penutup & doa kafaratul majelis', null],
        ];
        $agendas = [];
        foreach ($agendaTitles as $i => [$title, $discussion]) {
            $agendas[$i] = $past->agendas()->create([
                'sort_order' => $i + 1,
                'title' => $title,
                'discussion' => $discussion,
                'is_carry_over' => $i === 1,
            ]);
        }

        $d1 = $past->decisions()->create(['agenda_id' => $agendas[2]->id, 'content' => 'Jadwal mandi pagi kelas XI dimajukan 15 menit mulai pekan depan.', 'sort_order' => 1]);
        $d2 = $past->decisions()->create(['agenda_id' => $agendas[3]->id, 'content' => 'Santri yang belum mencapai target setoran mengikuti program takhassus ba\'da Subuh.', 'sort_order' => 2]);
        $d3 = $past->decisions()->create(['agenda_id' => $agendas[4]->id, 'content' => 'Kisi-kisi dan soal PTS dikumpulkan ke kurikulum paling lambat Kamis.', 'sort_order' => 3]);

        $past->actionItems()->create(['decision_id' => $d1->id, 'title' => 'Koordinasi jadwal mandi kelas XI dengan musyrif', 'pic_id' => $pengasuhan->id, 'due_date' => today()->subDays(2), 'priority' => 'tinggi', 'status' => ActionStatus::Proses, 'progress' => 50]);
        $past->actionItems()->create(['decision_id' => $d2->id, 'title' => 'Menyusun jadwal & daftar santri takhassus ba\'da Subuh', 'pic_id' => $tahfizh->id, 'due_date' => today()->addDays(3), 'priority' => 'sedang', 'success_indicator' => 'Daftar santri & jadwal diumumkan']);
        $past->actionItems()->create(['decision_id' => $d3->id, 'title' => 'Mengumpulkan kisi-kisi & soal PTS', 'pic_id' => $asatidz[0]->id, 'due_date' => today()->subDay(), 'priority' => 'tinggi', 'status' => ActionStatus::Selesai, 'progress' => 100, 'completed_at' => now()->subDay()]);
        $past->actionItems()->create(['decision_id' => $d3->id, 'title' => 'Menyusun jadwal pengawas PTS', 'pic_id' => $kepSma->id, 'due_date' => today()->addDays(1), 'priority' => 'sedang']);

        // ---------- Rapat pekan ini (terjadwal) ----------
        $next = Meeting::create([
            'meeting_type_id' => $asatidzSMAType->id,
            'unit_id' => $units['SMA']->id,
            'title' => 'Rapat Asatidz SMA Pekan III',
            'date' => today()->addDay(),
            'start_time' => '13:00',
            'end_time' => '14:30',
            'location' => 'Ruang Guru SMA',
            'chair_id' => $kepSma->id,
            'secretary_id' => $notulisSMA->id,
            'status' => MeetingStatus::Terjadwal,
            'created_by' => $notulisSMA->id,
        ]);

        foreach ($people as $user) {
            $next->participants()->create(['user_id' => $user->id]);
        }
        foreach (['Pembukaan, tilawah & doa', 'Evaluasi RTL rapat sebelumnya', 'Evaluasi KBM pekan ini', 'Perkembangan santri', 'Agenda pekan depan', 'Lain-lain', 'Penutup & doa kafaratul majelis'] as $i => $title) {
            $next->agendas()->create(['sort_order' => $i + 1, 'title' => $title, 'is_carry_over' => $i === 1]);
        }

        // ---------- Rapat pimpinan (terjadwal) ----------
        $rp = Meeting::create([
            'meeting_type_id' => $rapim->id,
            'unit_id' => $units['Pimpinan']->id,
            'title' => 'Rapat Pimpinan Bulanan',
            'date' => today()->addDays(3),
            'start_time' => '08:00',
            'location' => 'Kantor Pimpinan',
            'chair_id' => $mudir->id,
            'secretary_id' => $notulisSMA->id,
            'confidentiality' => 'rahasia',
            'status' => MeetingStatus::Terjadwal,
            'created_by' => $mudir->id,
        ]);
        foreach ([$mudir, $notulisSMA, $kepSma, $tahfizh, $pengasuhan] as $user) {
            $rp->participants()->create(['user_id' => $user->id]);
        }
        foreach (['Pembukaan, tilawah & doa', 'Evaluasi RTL rapat sebelumnya', 'Laporan kepala unit', 'Pembahasan program pekan depan', 'Lain-lain', 'Penutup & doa kafaratul majelis'] as $i => $title) {
            $rp->agendas()->create(['sort_order' => $i + 1, 'title' => $title, 'is_carry_over' => $i === 1]);
        }

        // ---------- Tanda tangan digital untuk rapat contoh yang sudah disahkan ----------
        foreach ([[$past, $kepSma], [$pastSmp, $kepSmp]] as [$meeting, $signer]) {
            MeetingSignature::issue($meeting->fresh(), $signer)->update(['signed_at' => $meeting->approved_at]);
        }
    }
}
