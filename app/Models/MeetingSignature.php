<?php

namespace App\Models;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tanda tangan digital pengesahan notulen: kode verifikasi + sidik jari (SHA-256) isi notulen.
 * Dicetak sebagai QR; dipindai → /verifikasi/{kode} (publik) menyatakan SAH / TIDAK BERLAKU.
 */
class MeetingSignature extends Model
{
    /** Tanpa karakter yang mudah tertukar (0/O, 1/I/L) agar mudah diketik ulang dari kertas. */
    protected const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    protected $fillable = ['meeting_id', 'code', 'content_hash', 'signed_by', 'signed_at', 'revoked_at', 'revoked_by'];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /** Terbitkan tanda tangan baru untuk rapat (dipanggil saat notulen disahkan). */
    public static function issue(Meeting $meeting, User $signer): self
    {
        return static::create([
            'meeting_id' => $meeting->id,
            'code' => static::generateCode(),
            'content_hash' => $meeting->contentFingerprint(),
            'signed_by' => $signer->id,
            'signed_at' => now(),
        ]);
    }

    public static function generateCode(): string
    {
        do {
            $groups = [];
            for ($g = 0; $g < 3; $g++) {
                $groups[] = collect(range(1, 4))->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])->implode('');
            }
            $code = 'JQ-'.implode('-', $groups);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** Rapikan kode yang diketik pengguna: huruf besar & tanpa spasi. */
    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', $code));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function url(): string
    {
        return route('verification.show', $this->code);
    }

    /** QR berisi tautan verifikasi, sebagai SVG (tajam saat dicetak / disimpan PDF). */
    public function qrSvg(): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 1,
            'drawLightModules' => false,
            'connectPaths' => true,
            'svgAddXmlHeader' => false,
            'cssClass' => 'qr-verifikasi',
        ]);

        return (new QRCode($options))->render($this->url());
    }
}
