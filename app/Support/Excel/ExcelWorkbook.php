<?php

namespace App\Support\Excel;

use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Writer;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pembungkus openspout agar semua ekspor Excel bergaya seragam:
 * judul + subjudul, header tebal berwarna (dibekukan & bisa difilter), tanggal sebagai tanggal Excel asli.
 * Ditulis streaming ke berkas sementara → hemat memori (cocok untuk shared hosting).
 */
class ExcelWorkbook
{
    protected Writer $writer;

    protected Options $options;

    protected string $path;

    protected int $sheetIndex = -1;

    protected int $rowNumber = 0;

    protected int $columnCount = 0;

    protected ?int $headerRow = null;

    protected Style $dateStyle;

    protected Style $dateTimeStyle;

    protected Style $wrapStyle;

    public function __construct(string $title)
    {
        $this->options = new Options;
        $this->options->setProperties(new Properties(
            title: $title,
            creator: config('rapat.institution'),
            lastModifiedBy: config('rapat.institution'),
        ));

        // Berkas sementara di dalam folder aplikasi: sebagian shared hosting membatasi akses /tmp (open_basedir)
        $tempFolder = storage_path('app/tmp');
        File::ensureDirectoryExists($tempFolder);
        $this->options->setTempFolder($tempFolder);
        $this->path = tempnam($tempFolder, 'rapat-xlsx-');
        $this->writer = new Writer($this->options);
        $this->writer->openToFile($this->path);

        $this->dateStyle = (new Style)->setFormat('dd/mm/yyyy');
        $this->dateTimeStyle = (new Style)->setFormat('dd/mm/yyyy hh:mm');
        $this->wrapStyle = (new Style)->setShouldWrapText()->setCellVerticalAlignment(CellVerticalAlignment::TOP);
    }

    public static function make(string $title): self
    {
        return new self($title);
    }

    /**
     * Mulai sheet baru. $widths = lebar kolom (dalam karakter), sekaligus menentukan jumlah kolom.
     *
     * @param  array<int, float|int>  $widths
     */
    public function sheet(string $name, array $widths): self
    {
        $this->finishSheet();

        $sheet = $this->sheetIndex < 0 ? $this->writer->getCurrentSheet() : $this->writer->addNewSheetAndMakeItCurrent();
        $this->sheetIndex++;
        $this->rowNumber = 0;
        $this->headerRow = null;
        $this->columnCount = count($widths);

        // Nama sheet Excel maks. 31 karakter & tanpa karakter khusus
        $sheet->setName(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $name), 0, 31));
        foreach (array_values($widths) as $i => $width) {
            $sheet->setColumnWidth((float) $width, $i + 1);
        }

        return $this;
    }

    /** Judul besar + baris subjudul (nama lembaga, filter, tanggal unduh), digabung selebar tabel. */
    public function title(string $title, array $subtitles = []): self
    {
        $this->mergedRow($title, (new Style)->setFontBold()->setFontSize(14));
        $this->mergedRow(config('rapat.institution'), (new Style)->setFontColor('475569'));
        foreach (array_filter($subtitles) as $subtitle) {
            $this->mergedRow($subtitle, (new Style)->setFontColor('475569'));
        }
        $this->mergedRow('Diunduh '.now()->translatedFormat('d F Y H:i').' WIB', (new Style)->setFontItalic()->setFontColor('94A3B8')->setFontSize(9));
        $this->blank();

        return $this;
    }

    /** Baris header tabel: tebal, latar hijau tua, dibekukan saat digulir, dan diberi autofilter. */
    public function header(array $labels): self
    {
        $border = new Border(new BorderPart(Border::BOTTOM, '065F46', Border::WIDTH_MEDIUM));
        $style = (new Style)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('047857')
            ->setShouldWrapText()
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder($border);

        $this->writer->addRow(Row::fromValues($labels, $style));
        $this->rowNumber++;
        $this->headerRow = $this->rowNumber;

        $this->writer->getCurrentSheet()->setSheetView((new SheetView)->setFreezeRow($this->rowNumber + 1));

        return $this;
    }

    /** Satu baris data. Tanggal (DateTimeInterface) jadi sel tanggal Excel asli. */
    public function row(array $values, bool $bold = false): self
    {
        $cells = array_map(function ($value) use ($bold) {
            if ($value instanceof DateTimeInterface) {
                $style = $value->format('H:i:s') === '00:00:00' ? $this->dateStyle : $this->dateTimeStyle;

                return Cell::fromValue($value, $bold ? (clone $style)->setFontBold() : $style);
            }
            // Teks panjang / berbaris dibungkus agar tidak menimpa kolom sebelah
            if (is_string($value) && (str_contains($value, "\n") || mb_strlen($value) > 40)) {
                return Cell::fromValue($value, $bold ? (clone $this->wrapStyle)->setFontBold() : $this->wrapStyle);
            }

            return Cell::fromValue(is_bool($value) ? ($value ? 'Ya' : 'Tidak') : $value, $bold ? (new Style)->setFontBold() : null);
        }, array_values($values));

        $this->writer->addRow(new Row($cells));
        $this->rowNumber++;

        return $this;
    }

    /** Pasangan label–nilai (untuk sheet info), label dicetak tebal. */
    public function pair(string $label, mixed $value): self
    {
        $value = match (true) {
            $value instanceof DateTimeInterface => Cell::fromValue($value, (clone $this->dateStyle)->setCellAlignment(CellAlignment::LEFT)),
            is_string($value) && mb_strlen($value) > 40 => Cell::fromValue($value, $this->wrapStyle),
            default => Cell::fromValue($value),
        };
        $this->writer->addRow(new Row([Cell::fromValue($label, (new Style)->setFontBold()), $value]));
        $this->rowNumber++;

        return $this;
    }

    public function note(string $text): self
    {
        return $this->mergedRow($text, (new Style)->setFontItalic()->setFontColor('64748B'));
    }

    public function blank(): self
    {
        $this->writer->addRow(Row::fromValues([]));
        $this->rowNumber++;

        return $this;
    }

    public function download(string $filename): StreamedResponse
    {
        $this->finishSheet();
        $this->writer->close();
        $path = $this->path;

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Untuk tes: tutup & kembalikan path berkas .xlsx. */
    public function save(): string
    {
        $this->finishSheet();
        $this->writer->close();

        return $this->path;
    }

    protected function mergedRow(string $text, Style $style): self
    {
        $this->writer->addRow(Row::fromValues([$text], $style));
        $this->rowNumber++;
        if ($this->columnCount > 1) {
            $this->options->mergeCells(0, $this->rowNumber, $this->columnCount - 1, $this->rowNumber, $this->sheetIndex);
        }

        return $this;
    }

    /** Pasang autofilter di tabel sheet yang sedang aktif (bila ada header & data). */
    protected function finishSheet(): void
    {
        if ($this->sheetIndex >= 0 && $this->headerRow && $this->rowNumber > $this->headerRow) {
            $this->writer->getCurrentSheet()->setAutoFilter(
                new AutoFilter(0, $this->headerRow, $this->columnCount - 1, $this->rowNumber)
            );
        }
        $this->headerRow = null;
    }
}
