<?php

namespace Tests\Feature;

use App\Enums\MeetingStatus;
use App\Livewire\ScheduleForm;
use App\Livewire\ScheduleIndex;
use App\Models\ActivityLog;
use App\Models\Meeting;
use App\Models\MeetingSchedule;
use App\Models\MeetingType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 06:00')); // Senin
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function dates(MeetingSchedule $schedule, string $from, string $to): array
    {
        return $schedule->occurrencesBetween(Carbon::parse($from), Carbon::parse($to))->map->toDateString()->all();
    }

    public function test_occurrence_patterns(): void
    {
        $weekly = new MeetingSchedule(['frequency' => 'mingguan', 'day_of_week' => 6, 'starts_on' => '2026-11-01', 'ends_on' => '2026-12-12']);
        $this->assertSame(['2026-11-28', '2026-12-05', '2026-12-12'], $this->dates($weekly, '2026-11-23', '2026-12-31'));
        $this->assertSame('Setiap Sabtu', $weekly->describe(false));

        // Dua pekan sekali berpatokan pada Sabtu pertama sejak tanggal mulai (7 Nov) → 21 Nov, 5 Des, 19 Des
        $biweekly = new MeetingSchedule(['frequency' => 'dua_mingguan', 'day_of_week' => 6, 'starts_on' => '2026-11-03']);
        $this->assertSame(['2026-12-05', '2026-12-19'], $this->dates($biweekly, '2026-11-23', '2026-12-31'));

        $firstMonday = new MeetingSchedule(['frequency' => 'bulanan', 'day_of_week' => 1, 'week_of_month' => 1, 'starts_on' => '2026-01-01']);
        $this->assertSame(['2026-12-07', '2027-01-04', '2027-02-01'], $this->dates($firstMonday, '2026-11-23', '2027-02-28'));
        $this->assertSame('Senin pekan ke-1 setiap bulan', $firstMonday->describe(false));

        $lastFriday = new MeetingSchedule(['frequency' => 'bulanan', 'day_of_week' => 5, 'week_of_month' => 5, 'starts_on' => '2026-01-01']);
        $this->assertSame(['2026-11-27', '2026-12-25', '2027-01-29'], $this->dates($lastFriday, '2026-11-23', '2027-01-31'));
        $this->assertSame('Jumat terakhir setiap bulan', $lastFriday->describe(false));
    }

    public function test_form_creates_schedule_and_upcoming_meetings_from_template(): void
    {
        $kepSma = $this->user('irfan@jogloquran.com');
        $notulis = $this->user('fauzan@jogloquran.com');
        $guru = $this->user('ustadz1@jogloquran.com');
        $type = MeetingType::where('name', 'Rapat Asatidz SMA Mingguan')->firstOrFail();
        $this->actingAs($kepSma);

        Livewire::test(ScheduleForm::class)
            ->set('meeting_type_id', (string) $type->id)
            ->assertSet('title', 'Rapat Asatidz SMA Mingguan')
            ->set('frequency', 'mingguan')
            ->set('day_of_week', '6')
            ->assertSee('Sabtu, 28 Nov 2026')            // pratinjau
            ->set('start_time', '13:00')
            ->set('end_time', '14:30')
            ->set('location', 'Ruang Guru SMA')
            ->set('secretary_id', (string) $notulis->id)
            ->set('participantIds', [(string) $guru->id])
            ->set('lead_days', 7)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('schedules.index'));

        $schedule = MeetingSchedule::firstOrFail();
        $this->assertSame($kepSma->id, $schedule->created_by);

        // Dalam 7 hari (23–30 Nov) hanya Sabtu 28 Nov
        $meeting = $schedule->meetings()->sole();
        $this->assertSame('2026-11-28', $meeting->date->toDateString());
        $this->assertSame('Rapat Asatidz SMA Mingguan · Sabtu, 28 Nov 2026', $meeting->title);
        $this->assertSame(MeetingStatus::Terjadwal, $meeting->status);
        $this->assertSame(['Pembukaan, tilawah & doa', 'Evaluasi RTL rapat sebelumnya', 'Evaluasi KBM pekan ini', 'Perkembangan santri', 'Agenda pekan depan', 'Lain-lain', 'Penutup & doa kafaratul majelis'],
            $meeting->agendas()->pluck('title')->all());
        $this->assertEqualsCanonicalizing([$kepSma->id, $notulis->id, $guru->id], $meeting->participants()->pluck('user_id')->all());

        // RTL rapat SMA sebelumnya ikut dievaluasi di rapat hasil jadwal
        $this->assertTrue($meeting->carryOverActionItems()->contains('title', 'Koordinasi jadwal mandi kelas XI dengan musyrif'));

        $this->assertTrue(ActivityLog::where('description', 'membuat jadwal rapat rutin')->exists());
        $this->assertTrue(ActivityLog::forMeeting($meeting->id)->where('description', 'menjadwalkan rapat otomatis dari jadwal rutin')->exists());
    }

    public function test_today_is_skipped_when_start_time_has_passed(): void
    {
        $schedule = new MeetingSchedule(['frequency' => 'mingguan', 'day_of_week' => 1, 'start_time' => '08:00', 'starts_on' => '2026-11-01']);

        // Senin 06:00 → rapat Senin 08:00 hari ini masih dibuat
        $this->assertSame('2026-11-23', $schedule->nextOccurrence()->toDateString());

        // Senin 09:00 → sudah lewat, rapat berikutnya Senin depan
        $this->travelTo(Carbon::parse('2026-11-23 09:00'));
        $this->assertSame('2026-11-30', $schedule->nextOccurrence()->toDateString());
    }

    public function test_daily_command_is_idempotent_and_respects_cancellations(): void
    {
        $schedule = MeetingSchedule::create([
            'title' => 'Rapat Pengasuhan', 'frequency' => 'mingguan', 'day_of_week' => 3, 'start_time' => '20:00',
            'chair_id' => $this->user('sigit@jogloquran.com')->id, 'starts_on' => '2026-11-01', 'lead_days' => 14,
        ]);

        $this->artisan('rapat:buat-dari-jadwal')->assertSuccessful();
        $this->assertSame(['2026-11-25', '2026-12-02'], $schedule->meetings()->orderBy('date')->get()->map(fn ($m) => $m->date->toDateString())->all());

        // Dijalankan ulang: tidak ada duplikat
        $this->artisan('rapat:buat-dari-jadwal')->expectsOutput('Tidak ada rapat baru yang perlu dibuat.')->assertSuccessful();
        $this->assertSame(2, $schedule->meetings()->count());

        // Rapat dibatalkan tidak dibuat ulang
        $schedule->meetings()->whereDate('date', '2026-11-25')->update(['status' => MeetingStatus::Batal->value]);
        $this->artisan('rapat:buat-dari-jadwal');
        $this->assertSame(2, $schedule->meetings()->count());

        // Pekan berikutnya: rapat 9 Des menyusul
        $this->travelTo(Carbon::parse('2026-11-30 05:00'));
        $this->artisan('rapat:buat-dari-jadwal');
        $this->assertTrue($schedule->meetings()->whereDate('date', '2026-12-09')->exists());
    }

    public function test_deactivate_and_delete_keep_existing_meetings(): void
    {
        $admin = $this->user('pimpinan@jogloquran.com');
        $this->actingAs($admin);
        $schedule = MeetingSchedule::create([
            'title' => 'Rapat Pimpinan Pekanan', 'frequency' => 'mingguan', 'day_of_week' => 2, 'start_time' => '08:00',
            'chair_id' => $admin->id, 'starts_on' => '2026-11-01', 'created_by' => $admin->id,
        ]);
        $schedule->generateUpcoming();
        $this->assertSame(1, $schedule->meetings()->count()); // Selasa 24 Nov

        Livewire::test(ScheduleIndex::class)->assertSee('Setiap Selasa')->call('toggleActive', $schedule->id);
        $this->assertFalse($schedule->fresh()->is_active);
        $this->travelTo(Carbon::parse('2026-11-30 05:00'));
        $this->artisan('rapat:buat-dari-jadwal');
        $this->assertSame(1, $schedule->meetings()->count());

        $meeting = $schedule->meetings()->first();
        Livewire::test(ScheduleIndex::class)->call('delete', $schedule->id);
        $this->assertModelMissing($schedule);
        $this->assertNull($meeting->fresh()->meeting_schedule_id);
    }

    public function test_permissions(): void
    {
        $this->actingAs($this->user('ustadz1@jogloquran.com'))->get('/jadwal-rutin')->assertForbidden();

        $schedule = MeetingSchedule::create([
            'title' => 'Rapat SMP', 'frequency' => 'mingguan', 'day_of_week' => 4, 'start_time' => '13:00',
            'unit_id' => $this->user('azzam@jogloquran.com')->unit_id,
            'chair_id' => $this->user('pimpinan@jogloquran.com')->id, 'starts_on' => '2026-11-01',
        ]);

        // Kepala SMP (unit sama) boleh mengelola; Kepala SMA tidak
        $this->actingAs($this->user('azzam@jogloquran.com'))->get(route('schedules.edit', $schedule->id))->assertOk();
        $this->actingAs($this->user('irfan@jogloquran.com'))->get(route('schedules.edit', $schedule->id))->assertForbidden();
        Livewire::test(ScheduleIndex::class)->assertDontSee('Rapat SMP');
    }
}
