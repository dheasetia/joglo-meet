<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipant extends Model
{
    protected $fillable = [
        'meeting_id', 'user_id', 'guest_name', 'guest_info', 'attendance_status', 'checked_in_at', 'note',
    ];

    /** Samakan dengan default kolom di migrasi agar model baru langsung punya status. */
    protected $attributes = [
        'attendance_status' => 'belum',
    ];

    protected function casts(): array
    {
        return [
            'attendance_status' => AttendanceStatus::class,
            'checked_in_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayName(): string
    {
        return $this->user?->name ?? $this->guest_name ?? '-';
    }

    public function displayInfo(): ?string
    {
        return $this->user ? ($this->user->jabatan ?? $this->user->unit?->name) : ($this->guest_info ? 'Tamu · '.$this->guest_info : 'Tamu');
    }
}
