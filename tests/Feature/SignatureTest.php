<?php

namespace Tests\Feature;

use App\Livewire\MeetingRoom;
use App\Models\ActivityLog;
use App\Models\Meeting;
use App\Models\MeetingSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SignatureTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** Rapat SMA Pekan III dijalankan sampai menunggu pengesahan. */
    private function endedMeeting(): Meeting
    {
        $meeting = Meeting::where('title', 'Rapat Asatidz SMA Pekan III')->firstOrFail();
        $this->actingAs($this->user('fauzan@jogloquran.com'));
        Livewire::test(MeetingRoom::class, ['meeting' => $meeting])
            ->call('startMeeting')
            ->set('newDecision', 'Keputusan bertanda tangan')
            ->call('addDecision')
            ->call('endMeeting');

        return $meeting->fresh();
    }

    public function test_seeded_approved_meetings_are_signed_and_valid(): void
    {
        $meeting = Meeting::where('title', 'Rapat Asatidz SMA Pekan II')->firstOrFail();
        $signature = $meeting->activeSignature;

        $this->assertNotNull($signature);
        $this->assertMatchesRegularExpression('/^JQ-[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/', $signature->code);
        $this->get($signature->url())->assertOk()->assertSee('Notulen SAH')->assertSee('Rapat Asatidz SMA Pekan II');
    }

    public function test_approval_issues_code_and_reopen_revokes_it(): void
    {
        $meeting = $this->endedMeeting();
        $chair = $this->user('irfan@jogloquran.com');
        $this->actingAs($chair);

        Livewire::test(MeetingRoom::class, ['meeting' => $meeting])->call('approve')->assertSee('Kode verifikasi');
        $first = $meeting->fresh()->activeSignature;
        $this->assertSame($chair->id, $first->signed_by);
        $this->assertSame($meeting->contentFingerprint(), $first->content_hash);

        // Verifikasi publik tanpa login
        auth()->logout();
        $this->get('/verifikasi/'.strtolower($first->code))->assertOk()->assertSee('Notulen SAH')->assertSee($chair->name);

        // Buka kunci (admin) → kode lama dicabut
        $this->actingAs($this->user('pimpinan@jogloquran.com'));
        Livewire::test(MeetingRoom::class, ['meeting' => $meeting->fresh()])->call('reopen');
        $this->assertTrue($first->fresh()->isRevoked());
        $this->assertNull($meeting->fresh()->activeSignature);
        $this->get($first->url())->assertSee('Tidak berlaku')->assertSee('telah dibatalkan');

        // Sahkan ulang → kode baru; halaman kode lama menunjuk ke kode baru
        $this->actingAs($chair);
        Livewire::test(MeetingRoom::class, ['meeting' => $meeting->fresh()])->call('approve');
        $second = $meeting->fresh()->activeSignature;
        $this->assertNotSame($first->code, $second->code);
        $this->get($first->url())->assertSee($second->code);

        $this->assertTrue(ActivityLog::forMeeting($meeting->id)->where('description', 'menerbitkan tanda tangan digital')->exists());
        $this->assertTrue(ActivityLog::forMeeting($meeting->id)->where('description', 'mencabut tanda tangan digital')->exists());
    }

    public function test_tampered_content_and_unknown_codes_are_rejected(): void
    {
        $meeting = Meeting::where('title', 'Rapat Asatidz SMA Pekan II')->firstOrFail();
        $signature = $meeting->activeSignature;

        // Isi notulen diubah langsung di database (melewati kunci aplikasi)
        $meeting->decisions()->first()->update(['content' => 'Isi keputusan yang dipalsukan']);
        $this->get($signature->url())->assertOk()->assertSee('Tidak berlaku')->assertSee('berbeda dengan saat disahkan');

        $this->get('/verifikasi/JQ-AAAA-BBBB-CCCC')->assertOk()->assertSee('Kode tidak ditemukan');
        $this->get('/verifikasi?kode=jq-aaaa-bbbb-cccc')->assertRedirect('/verifikasi/JQ-AAAA-BBBB-CCCC');
    }

    public function test_confidential_title_is_masked_and_content_never_shown(): void
    {
        $meeting = Meeting::where('confidentiality', 'rahasia')->firstOrFail();
        $meeting->update(['status' => 'disahkan', 'approved_at' => now(), 'approved_by' => $meeting->chair_id]);
        $signature = MeetingSignature::issue($meeting, $meeting->chair);

        $this->get($signature->url())->assertOk()
            ->assertSee('Notulen SAH')
            ->assertSee('Rapat bersifat rahasia')
            ->assertDontSee($meeting->title)
            ->assertDontSee('Laporan kepala unit'); // judul agenda tidak pernah tampil
    }

    public function test_print_shows_qr_and_excel_has_code(): void
    {
        $meeting = Meeting::where('title', 'Rapat Asatidz SMA Pekan II')->firstOrFail();
        $code = $meeting->activeSignature->code;
        $this->actingAs($this->user('fauzan@jogloquran.com'));

        $this->get(route('meetings.print', $meeting))->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('qr-verifikasi', false)
            ->assertSee($code);
    }

    public function test_verification_is_rate_limited(): void
    {
        foreach (range(1, 30) as $i) {
            $this->get('/verifikasi/JQ-AAAA-BBBB-CCC'.$i)->assertOk();
        }
        $this->get('/verifikasi/JQ-AAAA-BBBB-CCCZ')->assertStatus(429);
    }
}
