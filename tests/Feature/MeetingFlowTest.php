<?php

namespace Tests\Feature;

use App\Enums\ActionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\MeetingStatus;
use App\Livewire\ActionBoard;
use App\Livewire\MeetingForm;
use App\Livewire\MeetingRoom;
use App\Livewire\MyTasks;
use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MeetingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_all_pages_render_for_pimpinan(): void
    {
        $user = User::where('email', 'pimpinan@jogloquran.com')->first();
        $meeting = Meeting::first();

        $this->actingAs($user);

        foreach (['/', '/rapat', '/rapat/buat', "/rapat/{$meeting->id}", "/rapat/{$meeting->id}/cetak", '/rtl', '/rtl?view=kanban', '/tugas-saya', '/laporan/kehadiran'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_full_meeting_flow(): void
    {
        $notulis = User::where('email', 'fauzan@jogloquran.com')->first();
        $pic = User::where('email', 'pukat@jogloquran.com')->first();
        $this->actingAs($notulis);

        // 1. Buat rapat
        Livewire::test(MeetingForm::class)
            ->set('title', 'Rapat Uji')
            ->set('date', today()->format('Y-m-d'))
            ->set('start_time', '08:00')
            ->set('secretary_id', (string) $notulis->id)
            ->set('participantIds', [(string) $pic->id])
            ->call('save')
            ->assertHasNoErrors();

        $meeting = Meeting::where('title', 'Rapat Uji')->firstOrFail();
        $this->assertSame(MeetingStatus::Terjadwal, $meeting->status);
        $this->assertTrue($meeting->agendas()->where('is_carry_over', true)->exists());
        $this->assertSame(2, $meeting->participants()->count()); // notulis (chair & secretary) + pic

        $agenda = $meeting->agendas()->where('is_carry_over', false)->skip(1)->first();
        $picParticipant = $meeting->participants()->where('user_id', $pic->id)->first();

        // 2. Jalankan rapat: presensi, pembahasan, keputusan, RTL
        Livewire::test(MeetingRoom::class, ['meeting' => $meeting])
            ->call('startMeeting')
            ->call('setAttendance', $picParticipant->id, 'hadir')
            ->call('selectAgenda', $agenda->id)
            ->set("discussions.{$agenda->id}", 'Pembahasan uji')
            ->set('newDecision', 'Keputusan uji')
            ->call('addDecision')
            ->set('rtl.title', 'Tugas uji')
            ->set('rtl.pic_id', (string) $pic->id)
            ->call('saveRtl')
            ->assertHasNoErrors()
            ->call('endMeeting');

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Selesai, $meeting->status);
        $this->assertSame('Pembahasan uji', $agenda->fresh()->discussion);
        $this->assertSame(AttendanceStatus::Hadir, $picParticipant->fresh()->attendance_status);
        $item = ActionItem::where('title', 'Tugas uji')->firstOrFail();
        $this->assertSame($pic->id, $item->pic_id);

        // 3. PIC memperbarui progres
        $this->actingAs($pic);
        Livewire::test(MyTasks::class)
            ->call('openUpdate', $item->id)
            ->set('progressForm.status', 'proses')
            ->set('progressForm.progress', 40)
            ->call('saveUpdate')
            ->assertHasNoErrors();
        $this->assertSame(40, $item->fresh()->progress);

        // 4. Rapat berikutnya (tanpa jenis, unit sama) menampilkan RTL yang belum selesai
        $next = Meeting::create([
            'title' => 'Rapat Uji Lanjutan',
            'unit_id' => $meeting->unit_id,
            'date' => today()->addDay(),
            'start_time' => '08:00',
            'chair_id' => $notulis->id,
        ]);
        $this->assertTrue($next->carryOverActionItems()->contains('id', $item->id));

        // 5. Pengesahan oleh pimpinan rapat
        $this->actingAs($notulis);
        Livewire::test(MeetingRoom::class, ['meeting' => $meeting])->call('approve');
        $this->assertSame(MeetingStatus::Disahkan, $meeting->fresh()->status);
    }

    public function test_participant_cannot_edit_or_see_confidential_meeting(): void
    {
        $peserta = User::where('email', 'ustadz1@jogloquran.com')->first();
        $rahasia = Meeting::where('confidentiality', 'rahasia')->first();

        $this->actingAs($peserta)->get("/rapat/{$rahasia->id}")->assertForbidden();
        $this->actingAs($peserta)->get('/laporan/kehadiran')->assertForbidden();
    }

    public function test_action_board_filters(): void
    {
        $this->actingAs(User::where('email', 'pimpinan@jogloquran.com')->first());

        Livewire::test(ActionBoard::class)
            ->set('filter', 'terlambat')
            ->assertSee('Koordinasi jadwal mandi kelas XI')
            ->assertDontSee('Mengumpulkan kisi-kisi');
        $this->assertTrue(ActionItem::where('status', ActionStatus::Selesai->value)->exists());
    }
}
