<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Lampiran berkas/foto. Pemilik (attachable): Agenda, atau ActionItemUpdate (bukti progres RTL).
 * File disimpan privat dan hanya dibuka lewat AttachmentController yang mengecek hak akses.
 */
class Attachment extends Model
{
    use RecordsActivity {
        getActivitylogOptions as baseActivitylogOptions;
    }

    protected $fillable = [
        'attachable_type', 'attachable_id', 'meeting_id', 'uploaded_by',
        'disk', 'path', 'original_name', 'mime_type', 'size',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    protected static function booted(): void
    {
        // Hapus file fisik bersama datanya
        static::deleted(function (Attachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ---------- Bantuan tampilan ----------

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isEvidence(): bool
    {
        return $this->attachable_type === (new ActionItemUpdate)->getMorphClass();
    }

    /** Foto & PDF dibuka di tab baru; lainnya diunduh. */
    public function opensInline(): bool
    {
        return $this->isImage() || $this->mime_type === 'application/pdf';
    }

    public function url(bool $download = false): string
    {
        return route('attachments.show', ['attachment' => $this->id] + ($download ? ['unduh' => 1] : []));
    }

    public function humanSize(): string
    {
        return self::formatBytes($this->size);
    }

    /**
     * Ukuran berkas format Indonesia, mis. "193 B", "84 KB", "1,3 MB".
     * Tidak memakai Number::fileSize() karena itu butuh ekstensi intl (tak selalu ada di shared hosting).
     */
    public static function formatBytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;
        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        $number = number_format($bytes, $unit >= 2 ? 1 : 0, ',', '.');

        return preg_replace('/,0$/', '', $number).' '.$units[$unit];
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    /** Ikon heroicon sesuai jenis file. */
    public function icon(): string
    {
        return match (true) {
            $this->isImage() => 'photo',
            in_array(strtolower($this->extension()), ['xls', 'xlsx'], true) => 'table-cells',
            in_array(strtolower($this->extension()), ['ppt', 'pptx'], true) => 'presentation-chart-bar',
            default => 'document-text',
        };
    }

    // ---------- Hak akses ----------

    public function canView(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Bukti RTL: penanggung jawab RTL juga boleh melihat buktinya sendiri
        if ($this->isEvidence()) {
            $picId = ActionItem::query()
                ->whereKey(ActionItemUpdate::query()->whereKey($this->attachable_id)->value('action_item_id'))
                ->value('pic_id');
            if ($picId === $user->id) {
                return true;
            }
        }

        return (bool) Meeting::find($this->meeting_id)?->canView($user);
    }

    /** Lampiran agenda: pengelola rapat selama belum disahkan. Bukti RTL: hanya admin (bagian dari riwayat). */
    public function canDelete(User $user): bool
    {
        if ($this->isEvidence()) {
            return $user->isAdmin();
        }

        $meeting = Meeting::find($this->meeting_id);

        return $meeting && $meeting->canManage($user) && ! $meeting->isLocked();
    }

    // ---------- Log aktivitas ----------

    public static function activityLogName(): string
    {
        return 'notulen';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return $this->baseActivitylogOptions()->useLogName($this->isEvidence() ? 'rtl' : 'notulen');
    }

    protected function activityAttributes(): array
    {
        return ['original_name', 'size'];
    }

    protected function activityDescription(string $event): string
    {
        return match ($event) {
            'created' => $this->isEvidence() ? 'mengunggah bukti RTL' : 'mengunggah lampiran agenda',
            'deleted' => $this->isEvidence() ? 'menghapus bukti RTL' : 'menghapus lampiran agenda',
            default => 'mengubah lampiran',
        };
    }

    public function activityMeetingId(): ?int
    {
        return $this->meeting_id;
    }

    public function activityLabel(): ?string
    {
        return $this->original_name;
    }
}
