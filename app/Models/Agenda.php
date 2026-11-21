<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agenda extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'meeting_id', 'sort_order', 'title', 'presenter_id', 'duration_minutes', 'discussion', 'is_carry_over',
    ];

    protected function casts(): array
    {
        return ['is_carry_over' => 'boolean'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presenter_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class)->orderBy('sort_order');
    }

    // ---------- Log aktivitas ----------

    public static function activityLogName(): string
    {
        return 'notulen';
    }

    protected function activityAttributes(): array
    {
        return ['title', 'presenter_id', 'duration_minutes'];
    }

    /** Pembahasan tersimpan otomatis saat diketik; dicatat terpisah (dibatasi) oleh Ruang Rapat. */
    protected function activityIgnoredOnly(): array
    {
        return ['discussion', 'sort_order', 'is_carry_over'];
    }

    protected function activityDescription(string $event): string
    {
        return match ($event) {
            'created' => 'menambahkan agenda',
            'deleted' => 'menghapus agenda',
            default => 'mengubah agenda',
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
