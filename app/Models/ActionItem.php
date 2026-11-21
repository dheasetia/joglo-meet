<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Enums\ActionStatus;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

class ActionItem extends Model
{
    use RecordsActivity;

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

    /** Semua bukti dari seluruh riwayat progres RTL ini. */
    public function evidenceAttachments(): HasManyThrough
    {
        return $this->hasManyThrough(Attachment::class, ActionItemUpdate::class, 'action_item_id', 'attachable_id')
            ->where('attachments.attachable_type', (new ActionItemUpdate)->getMorphClass())
            ->oldest('attachments.id');
    }

    protected static function booted(): void
    {
        // Riwayat progres terhapus lewat cascade database; hapus dulu bukti & filenya
        static::deleting(fn (ActionItem $item) => $item->evidenceAttachments()->get()->each->delete());
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

    public function recordProgress(User $user, int $progress, ActionStatus $status, ?string $note = null, ?int $meetingId = null): ActionItemUpdate
    {
        return DB::transaction(function () use ($user, $progress, $status, $note, $meetingId) {
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

            return $this->updates()->create([
                'user_id' => $user->id,
                'meeting_id' => $meetingId,
                'progress' => $progress,
                'status' => $status,
                'note' => filled($note) ? $note : null,
            ]);
        });
    }

    // ---------- Log aktivitas ----------

    public static function activityLogName(): string
    {
        return 'rtl';
    }

    protected function activityAttributes(): array
    {
        return ['title', 'description', 'pic_id', 'due_date', 'priority', 'success_indicator'];
    }

    /** Perubahan progres/status dicatat lewat ActionItemUpdate (beserta catatannya). */
    protected function activityIgnoredOnly(): array
    {
        return ['progress', 'status', 'completed_at', 'decision_id'];
    }

    protected function activityDescription(string $event): string
    {
        return match ($event) {
            'created' => 'menambahkan RTL',
            'deleted' => 'menghapus RTL',
            default => 'mengubah RTL',
        };
    }

    public function activityMeetingId(): ?int
    {
        return $this->meeting_id;
    }

    public function activityLabel(): ?string
    {
        return $this->title;
    }
}
