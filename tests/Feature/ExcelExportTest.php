<?php

namespace Tests\Feature;

use App\Livewire\AttendanceReport;
use App\Livewire\MeetingIndex;
use App\Livewire\MeetingRoom;
use App\Models\Meeting;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;
use ZipArchive;

class ExcelExportTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** Simpan isi .xlsx ke berkas sementara, kembalikan path-nya. */
    private function store(string $binary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tes-xlsx-').'.xlsx';
        file_put_contents($path, $binary);
        $this->assertStringStartsWith('PK', $binary, 'Berkas harus berupa .xlsx (zip)');

        return $path;
    }

    /** Ambil berkas unduhan dari aksi Livewire. */
    private function livewireDownload(Testable $component): string
    {
        $download = $component->effects['download'] ?? null;
        $this->assertNotNull($download, 'Aksi harus menghasilkan unduhan');

        return $this->store(base64_decode($download['content']));
    }

    /** @return array<string, array<int, array<int, mixed>>> [nama sheet => baris] */
    private function readSheets(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();

        return $sheets;
    }

    /** Baris data setelah baris header (header dikenali dari sel pertama "No" / "Nama"). */
    private function dataRows(array $rows, string $firstHeader = 'No'): array
    {
        $index = collect($rows)->search(fn ($r) => ($r[0] ?? null) === $firstHeader);
        $this->assertNotFalse($index, "Header \"{$firstHeader}\" tidak ditemukan");

        return array_values(array_filter(array_slice($rows, $index + 1), fn ($r) => array_filter($r, fn ($v) => $v !== '' && $v !== null)));
    }

    public function test_meeting_list_export_follows_filters_and_visibility(): void
    {
        $this->actingAs($this->user('pimpinan@jogloquran.com'));
        $component = Livewire::test(MeetingIndex::class)->set('status', 'terjadwal')->call('exportExcel');
        $component->assertFileDownloaded('daftar-rapat-'.now()->format('Y-m-d').'.xlsx');

        $sheets = $this->readSheets($this->livewireDownload($component));
        $rows = $this->dataRows($sheets['Daftar rapat']);
        $titles = array_column($rows, 3);

        $this->assertSame(Meeting::where('status', 'terjadwal')->count(), count($rows));
        $this->assertContains('Rapat Asatidz SMA Pekan III', $titles);
        $this->assertContains('Rapat Pimpinan Bulanan (rahasia)', $titles);
        $this->assertNotContains('Rapat Asatidz SMA Pekan II', $titles); // disahkan → tersaring
        $this->assertInstanceOf(DateTimeInterface::class, $rows[0][1], 'Tanggal harus berupa tanggal Excel asli');
        $this->assertStringContainsString('Status: Terjadwal', implode(' ', array_column($sheets['Daftar rapat'], 0)));

        // Peserta biasa tidak melihat rapat rahasia yang tidak ia ikuti
        $this->actingAs($this->user('ustadz1@jogloquran.com'));
        $rows = $this->dataRows($this->readSheets($this->livewireDownload(Livewire::test(MeetingIndex::class)->call('exportExcel')))['Daftar rapat']);
        $this->assertNotContains('Rapat Pimpinan Bulanan (rahasia)', array_column($rows, 3));
    }

    public function test_attendance_export_has_summary_and_detail_sheets(): void
    {
        $this->actingAs($this->user('pimpinan@jogloquran.com'));
        $component = Livewire::test(AttendanceReport::class)
            ->set('from', today()->subMonth()->format('Y-m-d'))
            ->call('exportExcel');

        $sheets = $this->readSheets($this->livewireDownload($component));
        $this->assertSame(['Rekap', 'Detail'], array_keys($sheets));

        $rekap = collect($this->dataRows($sheets['Rekap']))->keyBy(1);
        $irfan = $rekap['Ust. Irfan Fachrurrazi'];
        $this->assertSame(1, $irfan[4]);       // undangan
        $this->assertSame(100, $irfan[10]);    // % kehadiran
        $this->assertTrue($rekap->has('Jumlah'), 'Baris jumlah harus ada');

        $detail = $this->dataRows($sheets['Detail'], 'Nama');
        $this->assertContains('Rapat Asatidz SMA Pekan II', array_column($detail, 3));
        $this->assertContains('Izin', array_column($detail, 4));
    }

    public function test_single_meeting_export_has_all_sheets_and_respects_access(): void
    {
        $meeting = Meeting::where('title', 'Rapat Asatidz SMA Pekan II')->firstOrFail();
        $this->actingAs($this->user('fauzan@jogloquran.com'));

        $response = $this->get(route('meetings.excel', $meeting))->assertOk()
            ->assertDownload('notulen-'.$meeting->date->format('Y-m-d').'-rapat-asatidz-sma-pekan-ii.xlsx');
        $path = $this->store($response->streamedContent());
        $sheets = $this->readSheets($path);

        $this->assertSame(['Info rapat', 'Daftar hadir', 'Keputusan & RTL', 'Evaluasi RTL'], array_keys($sheets));
        $this->assertContains('Evaluasi KBM pekan ini', array_column($this->dataRows($sheets['Info rapat']), 1));
        $this->assertCount(8, $this->dataRows($sheets['Daftar hadir']));
        $rtl = $this->dataRows($sheets['Keputusan & RTL']);
        $this->assertContains('Koordinasi jadwal mandi kelas XI dengan musyrif', array_column($rtl, 3));
        $this->assertInstanceOf(DateTimeInterface::class, collect($rtl)->firstWhere(3, 'Koordinasi jadwal mandi kelas XI dengan musyrif')[5]);

        // Header dibekukan & diberi autofilter
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet3.xml');
        $zip->close();
        $this->assertStringContainsString('state="frozen"', $xml);
        $this->assertStringContainsString('<autoFilter', $xml);

        // Rapat rahasia: bukan peserta → ditolak
        $secret = Meeting::where('confidentiality', 'rahasia')->firstOrFail();
        $this->actingAs($this->user('ustadz1@jogloquran.com'))->get(route('meetings.excel', $secret))->assertForbidden();
    }

    public function test_export_buttons_are_shown(): void
    {
        $this->actingAs($this->user('pimpinan@jogloquran.com'));
        $meeting = Meeting::firstOrFail();

        Livewire::test(MeetingIndex::class)->assertSee('Ekspor Excel');
        Livewire::test(AttendanceReport::class)->assertSee('Ekspor Excel');
        Livewire::test(MeetingRoom::class, ['meeting' => $meeting])->assertSeeHtml(route('meetings.excel', $meeting));
    }
}
