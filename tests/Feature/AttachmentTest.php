<?php

namespace Tests\Feature;

use App\Livewire\MeetingForm;
use App\Livewire\MeetingRoom;
use App\Livewire\MyTasks;
use App\Models\ActionItem;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function sma3(): Meeting
    {
        return Meeting::where('title', 'Rapat Asatidz SMA Pekan III')->firstOrFail();
    }

    private function roomOnAgenda(Meeting $meeting, string $agendaTitle)
    {
        $agenda = $meeting->agendas()->where('title', $agendaTitle)->firstOrFail();

        return [Livewire::test(MeetingRoom::class, ['meeting' => $meeting])->call('selectAgenda', $agenda->id), $agenda];
    }

    public function test_upload_to_agenda_resizes_photos_and_keeps_documents(): void
    {
        $this->actingAs($this->user('fauzan@jogloquran.com'));
        [$room, $agenda] = $this->roomOnAgenda($this->sma3(), 'Evaluasi KBM pekan ini');

        $room->set('agendaUploads', [
            UploadedFile::fake()->image('foto-kelas.jpg', 4000, 3000),
            UploadedFile::fake()->create('jadwal-pts.pdf', 300, 'application/pdf'),
        ])->assertHasNoErrors()->assertDispatched('toast');

        $this->assertSame(2, $agenda->attachments()->count());

        $photo = $agenda->attachments()->where('original_name', 'foto-kelas.jpg')->firstOrFail();
        Storage::disk('local')->assertExists($photo->path);
        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($photo->path));
        $this->assertSame(1920, max($width, $height), 'Foto harus diperkecil ke sisi terpanjang 1920 px');
        $this->assertSame($this->sma3()->id, $photo->meeting_id);

        $pdf = $agenda->attachments()->where('original_name', 'jadwal-pts.pdf')->firstOrFail();
        $this->assertSame(300 * 1024, $pdf->size);

        // Tampil di ruang rapat & tercatat di log
        $room->assertSee('foto-kelas.jpg')->assertSee('jadwal-pts.pdf');
        $this->assertTrue(ActivityLog::forMeeting($this->sma3()->id)->where('description', 'mengunggah lampiran agenda')->exists());
    }

    public function test_invalid_type_and_oversized_files_are_rejected(): void
    {
        $this->actingAs($this->user('fauzan@jogloquran.com'));
        [$room, $agenda] = $this->roomOnAgenda($this->sma3(), 'Evaluasi KBM pekan ini');

        $room->set('agendaUploads', [UploadedFile::fake()->create('virus.exe', 10)])
            ->assertHasErrors(['agendaUploads.0' => 'mimes']);
        // Berkas yang ditolak dibuang, jadi unggahan berikutnya dinilai sendiri
        $room->assertSet('agendaUploads', []);
        $room->set('agendaUploads', [UploadedFile::fake()->create('besar.pdf', 11 * 1024, 'application/pdf')])
            ->assertHasErrors(['agendaUploads.0' => 'max'])
            ->assertSee('melebihi 10 MB');
        $room->set('agendaUploads', [UploadedFile::fake()->create('kecil.pdf', 20, 'application/pdf')])->assertHasNoErrors();

        $this->assertSame(['kecil.pdf'], $agenda->attachments()->pluck('original_name')->all());
    }

    public function test_only_people_who_can_view_the_meeting_can_open_files(): void
    {
        $notulis = $this->user('fauzan@jogloquran.com');
        $this->actingAs($notulis);
        $secret = Meeting::where('confidentiality', 'rahasia')->firstOrFail();
        [$room] = $this->roomOnAgenda($secret, 'Laporan kepala unit');
        $room->set('agendaUploads', [UploadedFile::fake()->create('laporan.pdf', 50, 'application/pdf')]);
        $file = Attachment::firstOrFail();

        // Peserta rapat: dibuka inline (PDF), atau diunduh dengan ?unduh=1
        $this->get($file->url())->assertOk()->assertHeader('content-disposition', 'inline; filename=laporan.pdf');
        $this->get($file->url(download: true))->assertOk()->assertHeader('content-disposition', 'attachment; filename=laporan.pdf');

        // Bukan peserta rapat rahasia: ditolak
        $this->actingAs($this->user('ustadz1@jogloquran.com'))->get($file->url())->assertForbidden();
    }

    public function test_delete_removes_file_and_locked_meeting_rejects_uploads(): void
    {
        $this->actingAs($this->user('fauzan@jogloquran.com'));
        [$room, $agenda] = $this->roomOnAgenda($this->sma3(), 'Evaluasi KBM pekan ini');
        $room->set('agendaUploads', [UploadedFile::fake()->create('notula.docx', 20)]);
        $file = $agenda->attachments()->firstOrFail();

        $room->call('deleteAttachment', $file->id);
        $this->assertModelMissing($file);
        Storage::disk('local')->assertMissing($file->path);

        // Rapat yang sudah disahkan terkunci
        $locked = Meeting::where('title', 'Rapat Asatidz SMA Pekan II')->firstOrFail();
        [$lockedRoom] = $this->roomOnAgenda($locked, 'Evaluasi KBM pekan ini');
        $lockedRoom->set('agendaUploads', [UploadedFile::fake()->create('telat.pdf', 10, 'application/pdf')])->assertForbidden();
    }

    public function test_progress_evidence_is_saved_shown_in_next_meeting_and_printed(): void
    {
        $pic = $this->user('sigit@jogloquran.com');
        $item = ActionItem::where('title', 'like', 'Koordinasi jadwal mandi%')->firstOrFail();
        $this->actingAs($pic);

        Livewire::test(MyTasks::class)
            ->call('openUpdate', $item->id)
            ->set('progressForm.progress', 80)
            ->set('evidenceUploads', [UploadedFile::fake()->image('jadwal-mandi.png', 800, 600)])
            ->call('saveUpdate')
            ->assertHasNoErrors();

        $evidence = $item->evidenceAttachments()->firstOrFail();
        $this->assertSame('jadwal-mandi.png', $evidence->original_name);
        $this->assertTrue($evidence->isEvidence());
        $this->assertSame($item->meeting_id, $evidence->meeting_id);

        // PIC boleh membuka buktinya; bukti tak bisa dihapus selain oleh admin
        $this->get($evidence->url())->assertOk();
        $this->assertFalse($evidence->canDelete($pic));

        // Muncul di agenda "Evaluasi RTL" rapat berikutnya & cetak notulen
        $this->actingAs($this->user('fauzan@jogloquran.com'));
        [$room] = $this->roomOnAgenda($this->sma3(), 'Evaluasi RTL rapat sebelumnya');
        $room->assertSee('jadwal-mandi.png');
        $this->get("/rapat/{$this->sma3()->id}/cetak")->assertOk()->assertSee('Bukti: jadwal-mandi.png');
    }

    public function test_deleting_agenda_or_rtl_removes_their_files(): void
    {
        $notulis = $this->user('fauzan@jogloquran.com');
        $this->actingAs($notulis);
        $meeting = $this->sma3();
        [$room, $agenda] = $this->roomOnAgenda($meeting, 'Agenda pekan depan');
        $room->set('agendaUploads', [UploadedFile::fake()->create('rencana.xlsx', 15)]);
        $agendaFile = $agenda->attachments()->firstOrFail();

        // Hapus agenda lewat form rapat
        $form = Livewire::test(MeetingForm::class, ['meetingId' => $meeting->id]);
        $form->set('agendas', collect($form->get('agendas'))->reject(fn ($a) => $a['id'] === $agenda->id)->values()->all())
            ->call('save')->assertHasNoErrors();
        $this->assertModelMissing($agendaFile);
        Storage::disk('local')->assertMissing($agendaFile->path);

        // Hapus RTL beserta buktinya
        $item = ActionItem::where('title', 'like', 'Koordinasi jadwal mandi%')->firstOrFail();
        $update = $item->recordProgress($notulis, 60, \App\Enums\ActionStatus::Proses);
        $evidence = \App\Support\AttachmentStorage::store(UploadedFile::fake()->image('bukti.jpg'), $update, $item->meeting_id, $notulis);
        $item->delete();
        $this->assertModelMissing($evidence);
        Storage::disk('local')->assertMissing($evidence->path);
    }
}
