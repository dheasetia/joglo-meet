<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\User;
use GdImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menyimpan lampiran secara privat. Foto JPG/PNG/WEBP diperkecil otomatis
 * (diputar sesuai EXIF, sisi terpanjang maks. config rapat.attachments.image_max_side).
 */
class AttachmentStorage
{
    /** Aturan validasi untuk satu file unggahan. */
    public static function rules(): array
    {
        return [
            'file',
            'max:'.config('rapat.attachments.max_kb'),
            'mimes:'.implode(',', config('rapat.attachments.mimes')),
        ];
    }

    public static function maxFiles(): int
    {
        return (int) config('rapat.attachments.max_files');
    }

    /** Teks bantuan untuk form unggah, mis. "PDF, Word, … · maks. 10 MB per file". */
    public static function hint(): string
    {
        return 'PDF, Word, Excel, PowerPoint, JPG, PNG, WEBP · maks. '.round(config('rapat.attachments.max_kb') / 1024).' MB per file';
    }

    public static function store(UploadedFile $file, Model $attachable, ?int $meetingId, ?User $user): Attachment
    {
        $disk = config('rapat.attachments.disk');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $directory = 'lampiran/'.($meetingId ?? 'umum').'/'.now()->format('Y-m');
        $name = Str::uuid().'.'.$extension;

        $shrunk = self::shrinkImage($file, $extension);
        if ($shrunk !== null) {
            Storage::disk($disk)->put("{$directory}/{$name}", $shrunk);
            $size = strlen($shrunk);
        } else {
            // Dokumen / foto yang tidak diperkecil: disalin sebagai stream (hemat memori)
            Storage::disk($disk)->putFileAs($directory, $file, $name);
            $size = (int) $file->getSize();
        }
        unset($shrunk);

        return $attachable->attachments()->create([
            'meeting_id' => $meetingId,
            'uploaded_by' => $user?->id,
            'disk' => $disk,
            'path' => "{$directory}/{$name}",
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType(),
            'size' => $size,
        ]);
    }

    /**
     * Perkecil foto. Mengembalikan isi file baru, atau null bila bukan foto, hasilnya tidak lebih
     * kecil, atau memori server tidak cukup (file asli lalu disimpan apa adanya, tidak error).
     */
    protected static function shrinkImage(UploadedFile $file, string $extension): ?string
    {
        $type = match ($extension) {
            'jpg', 'jpeg' => 'jpeg',
            'png' => 'png',
            'webp' => 'webp',
            default => null,
        };
        $source = $file->getRealPath();

        if (! $type || ! $source || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        // Ukuran dibaca dari header saja, tanpa memuat gambar ke memori
        $info = @getimagesize($source);
        if (! $info) {
            return null;
        }
        [$width, $height] = $info;

        // Foto HP menyimpan arah putar di EXIF; terapkan agar tidak miring setelah disimpan ulang
        $orientation = $type === 'jpeg' ? self::exifOrientation($source) : 1;
        $rotated = in_array($orientation, [3, 6, 8], true);

        $maxSide = (int) config('rapat.attachments.image_max_side');
        $scale = min(1, $maxSide / max($width, $height, 1));

        // GD butuh ±5 byte per piksel per salinan gambar; foto 12 MP ≈ 60 MB
        $needed = (int) (($width * $height * ($rotated ? 2 : 1) + $width * $height * $scale * $scale) * 5) + 8 * 1024 * 1024;
        if (! self::ensureMemory($needed)) {
            return null;
        }

        $image = match ($type) {
            'jpeg' => @imagecreatefromjpeg($source),
            'png' => @imagecreatefrompng($source),
            'webp' => @imagecreatefromwebp($source),
        };
        if (! $image instanceof GdImage) {
            return null;
        }

        if ($rotated) {
            $image = self::rotate($image, $orientation);
            [$width, $height] = [imagesx($image), imagesy($image)];
        }

        if ($scale < 1) {
            $resized = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));
            if ($type !== 'jpeg') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }
            imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $width, $height);
            $image = $resized; // gambar besar dilepas dari memori di sini
            unset($resized);
        }

        $quality = (int) config('rapat.attachments.image_quality');
        ob_start();
        match ($type) {
            'jpeg' => imagejpeg($image, null, $quality),
            'png' => imagepng($image, null, 9),
            'webp' => imagewebp($image, null, $quality),
        };
        $encoded = ob_get_clean();
        unset($image);

        // Pakai hasil kompresi hanya bila memang lebih kecil (atau foto perlu diputar)
        return ($encoded !== false && (strlen($encoded) < (int) $file->getSize() || $rotated)) ? $encoded : null;
    }

    /** Pastikan sisa memori cukup; coba naikkan memory_limit bila perlu (sebagian hosting melarang). */
    protected static function ensureMemory(int $bytes): bool
    {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '' || $limit === '-1') {
            return true;
        }

        $limitBytes = self::toBytes($limit);
        $required = memory_get_usage(true) + $bytes;
        if ($required <= $limitBytes) {
            return true;
        }

        $raised = @ini_set('memory_limit', (string) (int) ceil($required / 1048576 + 16).'M');

        return $raised !== false && self::toBytes((string) ini_get('memory_limit')) >= $required;
    }

    protected static function toBytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    protected static function exifOrientation(string|false $path): int
    {
        if (! $path || ! function_exists('exif_read_data')) {
            return 1;
        }

        return (int) (@exif_read_data($path)['Orientation'] ?? 1);
    }

    protected static function rotate(GdImage $image, int $orientation): GdImage
    {
        $result = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $result instanceof GdImage ? $result : $image;
    }
}
