<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\MeetingStatus;
use App\Enums\Role;
use App\Livewire\ActionBoard;
use App\Livewire\AttendanceReport;
use App\Livewire\Dashboard;
use App\Livewire\MeetingForm;
use App\Livewire\MeetingIndex;
use App\Livewire\MeetingRoom;
use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\MeetingType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Menjalankan interaksi tombol/filter di setiap halaman Livewire
 * (pengganti klik manual di browser).
 */
class InteractionSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_meeting_index_filters_and_search(): void
    {
        $this->actingAs($this->user('pimpinan@jogloquran.com'));

        Livewire::test(MeetingIndex::class)
            ->set('search', 'PTS') // cari di isi pembahasan
            ->assertSee('Rapat Asatidz SMA Pekan II')
            ->assertDontSee('Rapat Pimpinan Bulanan')
            ->set('status', MeetingStatus::Terjadwal->value)
            ->assertDontSee('Rapat Asatidz SMA Pekan II')
            ->call('resetFilters')
            ->assertSee('Rapat Pimpinan Bulanan');
    }

    public function test_meeting_form_template_units_and_agenda_ordering(): void
    {
        $this->actingAs($this->user('fauzan@jogloquran.com'));
        $type = MeetingType::where('name', 'Rapat Pimpinan')->first();

        $form = Livewire::test(MeetingForm::class)
            ->set('meeting_type_id', (string) $type->id)
            ->assertSet('title', 'Rapat Pimpinan')
            ->assertSet('unit_id', (string) $type->unit_id);

        $titles = collect($form->get('agendas'))->pluck('title');
        $this->assertContains('Laporan kepala unit', $titles);

        $form->call('addAgenda')
            ->call('moveAgenda', 0, 1)
            ->call('toggleUnit', $type->unit_id);
        $this->assertNotEmpty($form->get('participantIds'));
        $this->assertSame('Pembukaan, tilawah & doa', $form->get('agendas')[1]['title']);

        $form->set('end_time', '07:00')->call('save')->assertHasErrors(['end_time']);
        $form->set('end_time', '10:00')->call('save')->assertHasNoErrors()->assertRedirect();

        $meeting = Meeting::where('title', 'Rapat Pimpinan')->latest('id')->first();
        $this->assertNotNull($meeting);

        // Ubah rapat yang sudah ada
        Livewire::test(MeetingForm::class, ['meetingId' => $meeting->id])
            ->assertSet('title', 'Rapat Pimpinan')
            ->set('title', 'Rapat Pimpinan (revisi)')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('Rapat Pimpinan (revisi)', $meeting->fresh()->title);
    }

    public function test_meeting_room_interactions_and_carry_over(): void
    {
        $this->actingAs($this->user('fauzan@jogloquran.com'));
        $meeting = Meeting::where('title', 'Rapat Asatidz SMA Pekan III')->first();
        $carryAgenda = $meeting->agendas()->where('is_carry_over', true)->first();
        $open = ActionItem::where('title', 'like', 'Koordinasi jadwal mandi%')->first();

        $room = Livewire::test(MeetingRoom::class, ['meeting' => $meeting])
            ->call('startMeeting')
            // tombol salin tautan presensi harus berisi URL asli, bukan "@js(...)" mentah
            ->assertSeeHtml('navigator.clipboard.writeText(')
            ->assertDontSeeHtml('@js(')
            // konfirmasi memakai modal, bukan dialog bawaan browser (wire:confirm)
            ->assertSeeHtml('role="alertdialog"')
            ->assertSee('Akhiri rapat?')
            ->assertDontSeeHtml('wire:confirm')
            ->call('selectAgenda', $carryAgenda->id)
            ->assertSee('Koordinasi jadwal mandi kelas XI')
            ->set("carry.{$open->id}.status", 'selesai')
            ->set("carry.{$open->id}.note", 'Sudah berjalan')
            ->call('saveCarry', $open->id)
            ->assertSee('Dicatat di rapat ini')
            ->set('guestName', 'Bapak Komite')
            ->call('addGuest')
            ->assertHasNoErrors()
            ->call('setAllPresent')
            ->set('notes', 'Catatan penutup');

        $this->assertSame(100, $open->fresh()->progress);
        $this->assertSame('Catatan penutup', $meeting->fresh()->notes);
        $this->assertSame(0, $meeting->participants()->where('attendance_status', AttendanceStatus::Belum->value)->count());

        // Keputusan: tambah, ubah, hapus
        $agenda = $meeting->agendas()->where('title', 'Evaluasi KBM pekan ini')->first();
        $room->call('selectAgenda', $agenda->id)
            ->set('newDecision', 'Keputusan A')
            ->call('addDecision')
            ->call('cancelEditing');
        $decision = $meeting->decisions()->where('content', 'Keputusan A')->first();
        $room->call('editDecision', $decision->id)
            ->set('editingDecisionText', 'Keputusan A (revisi)')
            ->call('updateDecision')
            ->assertSee('Keputusan A (revisi)')
            ->call('deleteDecision', $decision->id)
            ->assertDontSee('Keputusan A (revisi)');

        // Notulis tidak berhak mengesahkan; pimpinan rapat yang mengesahkan
        $room->call('endMeeting')->call('approve')->assertForbidden();
        $this->actingAs($this->user('irfan@jogloquran.com'));
        Livewire::test(MeetingRoom::class, ['meeting' => $meeting->fresh()])->call('approve');
        $this->assertSame(MeetingStatus::Disahkan, $meeting->fresh()->status);
        $this->get("/rapat/{$meeting->id}/cetak")->assertOk()->assertSee('Disahkan secara elektronik');
    }

    public function test_locked_meeting_cannot_be_edited(): void
    {
        $this->actingAs($this->user('fauzan@jogloquran.com'));
        $locked = Meeting::where('status', MeetingStatus::Disahkan->value)->first();

        Livewire::test(MeetingRoom::class, ['meeting' => $locked])
            ->set('newDecision', 'tidak boleh')
            ->call('addDecision')
            ->assertForbidden();
    }

    public function test_action_board_kanban_and_update_modal(): void
    {
        $this->actingAs($this->user('pimpinan@jogloquran.com'));
        $item = ActionItem::where('title', 'Menyusun jadwal pengawas PTS')->first();

        Livewire::test(ActionBoard::class)
            ->set('view', 'kanban')
            ->assertSee('Menyusun jadwal pengawas PTS')
            ->call('openUpdate', $item->id)
            ->assertSee('Simpan Progres')
            ->set('progressForm.status', 'selesai')
            ->call('saveUpdate')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame(100, $item->fresh()->progress);
    }

    public function test_dashboard_and_report_with_filters(): void
    {
        $this->actingAs($this->user('pimpinan@jogloquran.com'));

        Livewire::test(Dashboard::class)->assertSee('RTL Terlambat');

        Livewire::test(AttendanceReport::class)
            ->set('from', today()->subMonth()->format('Y-m-d'))
            ->assertSee('Ust. Irfan Fachrurrazi')
            ->set('type', (string) MeetingType::where('name', 'Rapat Pimpinan')->value('id'))
            ->assertSee('Belum ada data kehadiran');
    }

    public function test_qr_self_check_in(): void
    {
        $chair = $this->user('irfan@jogloquran.com');
        $peserta = $this->user('ustadz2@jogloquran.com');
        $meeting = Meeting::create([
            'title' => 'Rapat Hari Ini',
            'date' => today(),
            'start_time' => now()->format('H:i'),
            'chair_id' => $chair->id,
            'status' => MeetingStatus::Berlangsung,
        ]);

        $this->actingAs($peserta)->get("/hadir/{$meeting->checkin_token}")
            ->assertOk()->assertSee('Presensi tercatat');
        $this->assertTrue($meeting->participants()->where('user_id', $peserta->id)->first()->attendance_status->isPresent());

        $this->get('/hadir/token-salah')->assertNotFound();
    }

    public function test_admin_dashboard_shows_widgets(): void
    {
        // Akun dibuat di sini agar tidak bergantung pada email di seeder
        $admin = User::factory()->create(['role' => Role::SuperAdmin, 'is_active' => true]);

        // Widget Filament dimuat lazy: cek komponennya terpasang di halaman...
        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSeeLivewire(\Filament\Widgets\AccountWidget::class)
            ->assertSeeLivewire(\App\Filament\Widgets\MasterDataStats::class)
            ->assertSeeLivewire(\App\Filament\Widgets\LatestMeetings::class);

        // ...lalu render isinya
        Livewire::test(\App\Filament\Widgets\MasterDataStats::class)
            ->assertSee('Ringkasan')
            ->assertSee('Pengguna aktif')
            ->assertSee('RTL terlambat');
        Livewire::test(\App\Filament\Widgets\LatestMeetings::class)
            ->assertSee('Rapat terbaru')
            ->assertCanSeeTableRecords(Meeting::orderByDesc('date')->limit(5)->get());
    }

    public function test_admin_buttons_match_app_buttons(): void
    {
        $admin = User::factory()->create(['role' => Role::SuperAdmin, 'is_active' => true]);
        $this->actingAs($admin);

        // CSS penyeragam tombol ikut dimuat di panel
        $this->get('/admin/users')->assertOk()->assertSee('.fi-btn:not(.fi-outlined)', false);

        // Ikon & varian aksi: Tambah = hijau + plus, Ubah = abu + pencil-square, Hapus = merah + trash
        Livewire::test(\App\Filament\Resources\Users\Pages\ManageUsers::class)
            ->assertActionHasIcon('create', 'heroicon-o-plus')
            ->assertActionHasColor('create', 'primary')
            ->assertActionHasIcon(\Filament\Actions\Testing\TestAction::make('edit')->table($admin), 'heroicon-o-pencil-square')
            ->assertActionHasColor(\Filament\Actions\Testing\TestAction::make('edit')->table($admin), 'gray')
            ->assertActionHasIcon(\Filament\Actions\Testing\TestAction::make('delete')->table($admin), 'heroicon-o-trash')
            ->assertActionHasColor(\Filament\Actions\Testing\TestAction::make('delete')->table($admin), 'danger');
    }
}
