<?php

namespace Tests\Feature;

use App\Enums\MeetingStatus;
use App\Filament\Resources\WhatsappMessages\Pages\ManageWhatsappMessages;
use App\Jobs\SendWhatsappMessage;
use App\Livewire\MeetingForm;
use App\Livewire\MeetingRoom;
use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\MeetingSchedule;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\FonnteGateway;
use App\Services\WhatsApp\Gateway;
use App\Services\WhatsApp\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        // Nomor WA untuk sebagian pengguna; ustadz2 sengaja tanpa nomor
        $phones = [
            'fauzan@jogloquran.com' => '0812-1111-0001', 'irfan@jogloquran.com' => '+62 812 1111 0002',
            'pukat@jogloquran.com' => '6281211110003', 'sigit@jogloquran.com' => '81211110004',
            'ustadz1@jogloquran.com' => '081211110005', 'pimpinan@jogloquran.com' => '081211110006',
        ];
        foreach ($phones as $email => $phone) {
            User::where('email', $email)->update(['phone' => $phone]);
        }
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function sma3(): Meeting
    {
        return Meeting::where('title', 'Rapat Asatidz SMA Pekan III')->firstOrFail();
    }

    private function messages(string $type): \Illuminate\Support\Collection
    {
        return WhatsappMessage::where('type', $type)->get();
    }

    public function test_phone_normalization(): void
    {
        $this->assertSame('6281211110001', Phone::normalize('0812-1111-0001'));
        $this->assertSame('6281211110002', Phone::normalize('+62 812 1111 0002'));
        $this->assertSame('6281211110004', Phone::normalize('81211110004'));
        $this->assertNull(Phone::normalize('12345'));
        $this->assertNull(Phone::normalize(null));
        $this->assertSame('0812-1111-0001', Phone::display('6281211110001'));
    }

    public function test_fonnte_gateway_sends_full_number_with_country_code_zero(): void
    {
        // Respons berurutan: sukses, lalu gagal karena token salah (contoh dari dokumentasi Fonnte)
        Http::fakeSequence('api.fonnte.com/*')
            ->push(['status' => true, 'detail' => 'success! message in queue', 'id' => ['80367170'], 'process' => 'pending'])
            ->push(['status' => false, 'reason' => 'token invalid']);

        $result = (new FonnteGateway('TOKEN-ABC', 'https://api.fonnte.com/send'))->send('6281211110001', 'Halo');

        $this->assertTrue($result->ok);
        $this->assertSame('80367170', $result->providerId);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.fonnte.com/send'
            && $r->hasHeader('Authorization', 'TOKEN-ABC')
            && $r['target'] === '6281211110001'
            && $r['countryCode'] === '0'
            && $r['message'] === 'Halo');

        $failed = (new FonnteGateway('SALAH', 'https://api.fonnte.com/send'))->send('6281211110001', 'Halo');
        $this->assertFalse($failed->ok);
        $this->assertFalse($failed->retryable, 'Token salah tidak perlu dicoba ulang');
        $this->assertSame('Fonnte: token invalid', $failed->error);
    }

    public function test_invitation_button_queues_once_per_participant(): void
    {
        $notulis = $this->user('fauzan@jogloquran.com');
        $this->actingAs($notulis);

        Livewire::test(MeetingRoom::class, ['meeting' => $this->sma3()])
            ->call('sendInvitations')
            ->assertDispatched('toast');

        $invites = $this->messages('undangan');
        $this->assertNotContains($notulis->id, $invites->pluck('user_id'), 'Penekan tombol tidak diundang dirinya sendiri');
        $this->assertSame(WhatsappMessage::SKIPPED, $invites->firstWhere('user_id', $this->user('ustadz2@jogloquran.com')->id)->status);
        $queued = $invites->where('status', WhatsappMessage::QUEUED);
        $this->assertTrue($queued->contains('phone', '6281211110002'));
        $this->assertStringContainsString('*Rapat Asatidz SMA Pekan III*', $queued->first()->message);
        $this->assertStringContainsString(route('meetings.show', $this->sma3()), $queued->first()->message);
        Queue::assertPushed(SendWhatsappMessage::class, $queued->count());

        // Ditekan lagi: tidak ada pesan ganda
        Livewire::test(MeetingRoom::class, ['meeting' => $this->sma3()])->call('sendInvitations');
        $this->assertSame($invites->count(), $this->messages('undangan')->count());
    }

    public function test_job_sends_with_gateway_and_marks_status(): void
    {
        $this->actingAs($this->user('fauzan@jogloquran.com'));
        Livewire::test(MeetingRoom::class, ['meeting' => $this->sma3()])->call('sendInvitations');
        $message = WhatsappMessage::where('status', WhatsappMessage::QUEUED)->firstOrFail();

        (new SendWhatsappMessage($message->id))->handle(app(Gateway::class)); // driver log
        $this->assertSame(WhatsappMessage::SENT, $message->fresh()->status);
        $this->assertStringStartsWith('log-', $message->fresh()->provider_id);
    }

    public function test_changes_cancellation_and_new_participants_are_notified(): void
    {
        $notulis = $this->user('fauzan@jogloquran.com');
        $this->actingAs($notulis);
        $meeting = $this->sma3();
        Livewire::test(MeetingRoom::class, ['meeting' => $meeting])->call('sendInvitations');

        // Ubah jam & tambah peserta baru (Kepala Pengasuhan)
        $form = Livewire::test(MeetingForm::class, ['meetingId' => $meeting->id])
            ->set('start_time', '15:00')->set('end_time', '16:30');
        $form->set('participantIds', [...$form->get('participantIds'), (string) $this->user('sigit@jogloquran.com')->id])
            ->call('save')->assertHasNoErrors();

        $changes = $this->messages('perubahan');
        $this->assertTrue($changes->isNotEmpty());
        $this->assertStringContainsString('~13:00 – 14:30 WIB~ → *15:00 – 16:30 WIB*', $changes->first()->message);
        $this->assertTrue($this->messages('undangan')->contains('user_id', $this->user('sigit@jogloquran.com')->id), 'Peserta baru ikut diundang');

        Livewire::test(MeetingRoom::class, ['meeting' => $meeting->fresh()])->call('cancelMeeting');
        $this->assertTrue($this->messages('pembatalan')->contains('user_id', $this->user('irfan@jogloquran.com')->id));
        $this->assertStringContainsString('*dibatalkan*', $this->messages('pembatalan')->first()->message);
    }

    public function test_approval_and_new_rtl_notify(): void
    {
        $notulis = $this->user('fauzan@jogloquran.com');
        $pic = $this->user('pukat@jogloquran.com');
        $this->actingAs($notulis);
        $meeting = $this->sma3();

        Livewire::test(MeetingRoom::class, ['meeting' => $meeting])
            ->call('startMeeting')
            ->set('newDecision', 'Keputusan WA')
            ->call('addDecision')
            ->set('rtl.title', 'RTL untuk Ust. Pukat')
            ->set('rtl.pic_id', (string) $pic->id)
            ->call('saveRtl')
            ->call('endMeeting');

        $rtl = $this->messages('rtl_baru')->sole();
        $this->assertSame($pic->id, $rtl->user_id);
        $this->assertStringContainsString('*RTL untuk Ust. Pukat*', $rtl->message);

        $this->actingAs($this->user('irfan@jogloquran.com'));
        Livewire::test(MeetingRoom::class, ['meeting' => $meeting->fresh()])->call('approve');
        $code = $meeting->fresh()->activeSignature->code;
        $approved = $this->messages('notulen_disahkan');
        $this->assertTrue($approved->isNotEmpty());
        $this->assertStringContainsString($code, $approved->first()->message);
    }

    public function test_daily_reminders_are_sent_once(): void
    {
        $this->travelTo(Carbon::parse('2026-11-21 07:00'));
        $meeting = $this->sma3(); // seeder: besok
        $dueTomorrow = ActionItem::where('title', 'Menyusun jadwal pengawas PTS')->firstOrFail();
        $overdue = ActionItem::where('title', 'like', 'Koordinasi jadwal mandi%')->firstOrFail();
        $dueTomorrow->update(['due_date' => '2026-11-22']);
        $overdue->update(['due_date' => '2026-11-20']);

        $this->artisan('rapat:kirim-pengingat')->assertSuccessful();
        $this->artisan('rapat:kirim-pengingat')->assertSuccessful(); // ulang: tanpa ganda

        $this->assertSame($meeting->participants()->whereNotNull('user_id')->count(), $this->messages('pengingat_rapat')->count());
        $this->assertSame($dueTomorrow->pic_id, $this->messages('rtl_h1')->sole()->user_id);
        $this->assertSame($overdue->pic_id, $this->messages('rtl_terlambat')->sole()->user_id);
        $this->assertStringContainsString('melewati deadline', $this->messages('rtl_terlambat')->sole()->message);
    }

    public function test_scheduled_meetings_send_invitations_automatically(): void
    {
        $this->travelTo(Carbon::parse('2026-11-23 05:00'));
        $schedule = MeetingSchedule::create([
            'title' => 'Rapat Tahfizh', 'frequency' => 'mingguan', 'day_of_week' => 4, 'start_time' => '20:00',
            'chair_id' => $this->user('pukat@jogloquran.com')->id, 'participant_ids' => [$this->user('ustadz1@jogloquran.com')->id],
            'starts_on' => '2026-11-01',
        ]);
        $meeting = $schedule->generateUpcoming()->sole();

        $this->assertEqualsCanonicalizing(
            [$this->user('pukat@jogloquran.com')->id, $this->user('ustadz1@jogloquran.com')->id],
            WhatsappMessage::where('meeting_id', $meeting->id)->where('type', 'undangan')->pluck('user_id')->all(),
        );
    }

    public function test_admin_outbox_page_and_test_message(): void
    {
        $admin = User::factory()->create(['role' => \App\Enums\Role::SuperAdmin, 'is_active' => true]);
        $this->actingAs($admin)->get('/admin/whatsapp-messages')->assertOk()->assertSee('Mode uji coba');

        Livewire::test(ManageWhatsappMessages::class)
            ->callAction('kirimUji', ['phone' => '081299990000', 'message' => 'Tes WA'])
            ->assertHasNoActionErrors();
        $this->assertSame('6281299990000', $this->messages('uji')->sole()->phone);

        Livewire::test(ManageWhatsappMessages::class)
            ->callAction('kirimUji', ['phone' => '12', 'message' => 'Tes'])
            ->assertHasActionErrors(['phone']);
    }
}
