<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\MeetingForm;
use App\Livewire\MeetingHistory;
use App\Livewire\MeetingRoom;
use App\Livewire\MyTasks;
use App\Models\ActionItem;
use App\Models\ActivityLog;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** @return array<int, string> deskripsi log rapat, urut lama → baru */
    private function descriptionsFor(Meeting $meeting): array
    {
        return ActivityLog::query()->forMeeting($meeting->id)->oldest('id')->pluck('description')->all();
    }

    public function test_seeder_does_not_create_logs(): void
    {
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_meeting_room_actions_are_logged_in_indonesian(): void
    {
        $notulis = $this->user('fauzan@jogloquran.com');
        $chair = $this->user('irfan@jogloquran.com');
        $meeting = Meeting::where('title', 'Rapat Asatidz SMA Pekan III')->firstOrFail();
        $agenda = $meeting->agendas()->where('title', 'Evaluasi KBM pekan ini')->firstOrFail();
        $participant = $meeting->participants()->whereNotNull('user_id')->where('user_id', '!=', $notulis->id)->firstOrFail();

        $this->actingAs($notulis);
        Livewire::test(MeetingRoom::class, ['meeting' => $meeting])
            ->call('startMeeting')
            ->call('setAttendance', $participant->id, 'izin')
            ->call('selectAgenda', $agenda->id)
            // autosave pembahasan berkali-kali → hanya sekali tercatat
            ->set("discussions.{$agenda->id}", 'Draf 1')
            ->set("discussions.{$agenda->id}", 'Draf 2')
            ->set("discussions.{$agenda->id}", 'Draf 3')
            ->set('newDecision', 'Keputusan uji log')
            ->call('addDecision')
            ->set('rtl.title', 'RTL uji log')
            ->set('rtl.pic_id', (string) $chair->id)
            ->call('saveRtl')
            ->call('setAllPresent')
            ->call('endMeeting');

        $this->actingAs($chair);
        Livewire::test(MeetingRoom::class, ['meeting' => $meeting->fresh()])->call('approve');

        $descriptions = $this->descriptionsFor($meeting);
        foreach ([
            'memulai rapat', 'mengubah presensi', 'mengubah pembahasan agenda', 'mencatat keputusan',
            'menambahkan RTL', 'menandai semua peserta yang belum presensi hadir', 'mengakhiri rapat', 'mengesahkan notulen',
        ] as $expected) {
            $this->assertContains($expected, $descriptions, "Log \"{$expected}\" tidak ditemukan");
        }
        $this->assertSame(1, collect($descriptions)->filter(fn ($d) => $d === 'mengubah pembahasan agenda')->count());

        // Perubahan status tercatat lama → baru dengan label Indonesia
        $start = ActivityLog::forMeeting($meeting->id)->where('description', 'memulai rapat')->firstOrFail();
        $this->assertSame($notulis->id, $start->causer_id);
        $this->assertContains(['field' => 'Status', 'old' => 'Terjadwal', 'new' => 'Berlangsung'], $start->changeLines());

        $presence = ActivityLog::forMeeting($meeting->id)->where('description', 'mengubah presensi')->firstOrFail();
        $this->assertSame($participant->user->name, $presence->subjectLabel());
        $this->assertContains(['field' => 'Kehadiran', 'old' => 'Belum presensi', 'new' => 'Izin'], $presence->changeLines());

        $approved = ActivityLog::forMeeting($meeting->id)->where('description', 'mengesahkan notulen')->firstOrFail();
        $this->assertSame($chair->id, $approved->causer_id);
    }

    public function test_meeting_form_logs_summaries_instead_of_one_log_per_row(): void
    {
        $notulis = $this->user('fauzan@jogloquran.com');
        $peserta = $this->user('ustadz1@jogloquran.com');
        $this->actingAs($notulis);

        Livewire::test(MeetingForm::class)
            ->set('title', 'Rapat Uji Log')
            ->set('participantIds', [(string) $peserta->id])
            ->call('save')
            ->assertHasNoErrors();

        $meeting = Meeting::where('title', 'Rapat Uji Log')->firstOrFail();
        $descriptions = $this->descriptionsFor($meeting);

        $this->assertContains('membuat rapat', $descriptions);
        $this->assertContains('mengundang peserta', $descriptions);
        $this->assertContains('menyusun agenda rapat', $descriptions);
        $this->assertNotContains('menambahkan agenda', $descriptions);
        $this->assertNotContains('menambahkan peserta', $descriptions);

        $invite = ActivityLog::forMeeting($meeting->id)->where('description', 'mengundang peserta')->firstOrFail();
        $this->assertStringContainsString($peserta->name, collect($invite->changeLines())->firstWhere('field', 'Ditambahkan')['new']);

        // Ubah: ganti judul agenda & hapus satu agenda → satu ringkasan "mengubah daftar agenda"
        $form = Livewire::test(MeetingForm::class, ['meetingId' => $meeting->id]);
        $agendas = $form->get('agendas');
        $agendas[0]['title'] = 'Pembukaan';
        array_pop($agendas);
        $form->set('agendas', $agendas)->call('save')->assertHasNoErrors();

        $change = ActivityLog::forMeeting($meeting->id)->where('description', 'mengubah daftar agenda')->firstOrFail();
        $fields = collect($change->changeLines())->pluck('new', 'field');
        $this->assertStringContainsString('→ Pembukaan', $fields['Diubah']);
        $this->assertNotEmpty($fields['Dihapus']);
    }

    public function test_progress_update_is_logged_with_note(): void
    {
        $pic = $this->user('pukat@jogloquran.com');
        $item = ActionItem::where('pic_id', $pic->id)->firstOrFail();
        $this->actingAs($pic);

        Livewire::test(MyTasks::class)
            ->call('openUpdate', $item->id)
            ->set('progressForm.status', 'proses')
            ->set('progressForm.progress', 60)
            ->set('progressForm.note', 'Daftar santri sudah 60%')
            ->call('saveUpdate');

        $log = ActivityLog::where('description', 'memperbarui progres RTL')->latest('id')->firstOrFail();
        $this->assertSame($item->title, $log->subjectLabel());
        $this->assertSame($item->meeting_id, $log->getProperty('meeting_id'));
        $lines = collect($log->changeLines())->pluck('new', 'field');
        $this->assertSame('60%', $lines['Progres']);
        $this->assertSame('Daftar santri sudah 60%', $lines['Catatan']);
    }

    public function test_passwords_are_never_logged(): void
    {
        $user = $this->user('ustadz2@jogloquran.com');
        $user->update(['password' => 'rahasia-baru-123', 'jabatan' => 'Wali kelas']);

        $log = ActivityLog::where('description', 'mengubah pengguna')->latest('id')->firstOrFail();
        $raw = json_encode($log->attribute_changes);
        $this->assertStringNotContainsString('password', $raw);
        $this->assertStringContainsString('Wali kelas', $raw);
    }

    public function test_history_tab_is_only_for_meeting_managers(): void
    {
        $meeting = Meeting::where('title', 'Rapat Asatidz SMA Pekan III')->firstOrFail();

        $this->actingAs($this->user('fauzan@jogloquran.com'));
        Livewire::test(MeetingRoom::class, ['meeting' => $meeting])->call('startMeeting')->assertSee('Riwayat');
        Livewire::test(MeetingHistory::class, ['meetingId' => $meeting->id])
            ->assertSee('memulai rapat')
            ->assertSee('Berlangsung');

        // Peserta biasa: tab tidak tampil & komponen ditolak
        $this->actingAs($this->user('ustadz1@jogloquran.com'));
        Livewire::test(MeetingRoom::class, ['meeting' => $meeting])->assertDontSee('Riwayat Aktivitas')->assertDontSeeHtml("tab = 'riwayat'");
        Livewire::test(MeetingHistory::class, ['meetingId' => $meeting->id])->assertForbidden();
    }

    public function test_login_is_logged(): void
    {
        $this->post('/login', ['email' => 'pimpinan@jogloquran.com', 'password' => 'password'])->assertRedirect('/');
        $this->assertTrue(ActivityLog::where('description', 'masuk ke aplikasi')->where('log_name', 'akses')->exists());
    }

    public function test_admin_page_lists_logs(): void
    {
        $this->user('ustadz2@jogloquran.com')->update(['jabatan' => 'Wali kelas']);

        $admin = User::factory()->create(['role' => Role::SuperAdmin, 'is_active' => true]);
        $this->actingAs($admin)->get('/admin/activity-logs')->assertOk()->assertSee('Log Aktivitas');
        Livewire::test(\App\Filament\Resources\ActivityLogs\Pages\ManageActivityLogs::class)
            ->assertCanSeeTableRecords(ActivityLog::all())
            ->assertSee('mengubah pengguna')
            ->assertSee('Wali kelas');
    }
}
