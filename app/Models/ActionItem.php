<?php

namespace App\Models;

use App\Enums\ActionStatus;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ActionItem extends Model
{
    protected $fillable = [
        'meeting_id', 'decision_id', 'title', 'description', 'success_indicator',
        'pic_id', 'due_date', 'priority', 'status', 'progress', 'completed_at',
    ];

    /** Samakan dengan default kolom di migrasi. */
    protected $attributes = [
        'status' => 'belum',
        'priority' => 'sedang',
        'progress' => 0,
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'status' => ActionStatus::class,
            'priority' => Priority::class,
            'progress' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ActionItemUpdate::class)->latest();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(fn ($s) => $s->value, ActionStatus::openCases()));
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('due_date', '<', today());
    }

    /** RTL yang boleh dilihat pengguna: miliknya, atau dari rapat yang bisa ia lihat. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('pic_id', $user->id)
                ->orWhereHas('meeting', fn (Builder $m) => $m->visibleTo($user));
        });
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_date->lt(today());
    }

    public function daysLeftLabel(): string
    {
        if (! $this->status->isOpen()) {
            return $this->completed_at ? 'Selesai '.$this->completed_at->translatedFormat('d M Y') : $this->status->label();
        }

        $diff = (int) today()->diffInDays($this->due_date, false);

        return match (true) {
            $diff < 0 => 'Terlambat '.abs($diff).' hari',
            $diff === 0 => 'Deadline hari ini',
            $diff === 1 => 'Besok',
            default => $diff.' hari lagi',
        };
    }

    public function canUpdate(User $user): bool
    {
        if ($user->id === $this->pic_id || $user->isAdmin()) {
            return true;
        }

        return (bool) $this->loadMissing('meeting')->meeting?->canManage($user);
    }

    public function recordProgress(User $user, int $progress, ActionStatus $status, ?string $note = null, ?int $meetingId = null): void
    {
        DB::transaction(function () use ($user, $progress, $status, $note, $meetingId) {
            $progress = max(0, min(100, $progress));

            if ($status === ActionStatus::Selesai) {
                $progress = 100;
            } elseif ($status === ActionStatus::Belum && $progress > 0) {
                $status = ActionStatus::Proses;
            }

            $this->update([
                'progress' => $progress,
                'status' => $status,
                'completed_at' => $status === ActionStatus::Selesai ? ($this->completed_at ?? now()) : null,
            ]);

            $this->updates()->create([
                'user_id' => $user->id,
                'meeting_id' => $meetingId,
                'progress' => $progress,
                'status' => $status,
                'note' => filled($note) ? $note : null,
            ]);
        });
    }
}
