<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kotak keluar & riwayat notifikasi WhatsApp. */
class WhatsappMessage extends Model
{
    public const QUEUED = 'antre';
    public const SENT = 'terkirim';
    public const FAILED = 'gagal';
    public const SKIPPED = 'dilewati';

    public const STATUSES = [
        self::QUEUED => 'Antre',
        self::SENT => 'Terkirim',
        self::FAILED => 'Gagal',
        self::SKIPPED => 'Dilewati',
    ];

    public const TYPES = [
        'undangan' => 'Undangan rapat',
        'perubahan' => 'Perubahan rapat',
        'pembatalan' => 'Pembatalan rapat',
        'pengingat_rapat' => 'Pengingat rapat (H-1)',
        'notulen_disahkan' => 'Notulen disahkan',
        'rtl_baru' => 'RTL baru',
        'rtl_h1' => 'Pengingat deadline RTL',
        'rtl_terlambat' => 'RTL terlambat',
        'uji' => 'Pesan uji',
    ];

    protected $fillable = [
        'user_id', 'phone', 'type', 'meeting_id', 'action_item_id', 'message', 'status',
        'provider_id', 'error', 'attempts', 'sent_at', 'dedupe_key', 'created_by',
    ];

    protected $attributes = ['status' => self::QUEUED, 'attempts' => 0];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function actionItem(): BelongsTo
    {
        return $this->belongsTo(ActionItem::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
