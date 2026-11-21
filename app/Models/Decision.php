<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Decision extends Model
{
    use RecordsActivity;

    protected $fillable = ['meeting_id', 'agenda_id', 'content', 'sort_order'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(Agenda::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class)->orderBy('due_date');
    }

    // ---------- Log aktivitas ----------

    public static function activityLogName(): string
    {
        return 'notulen';
    }

    protected function activityAttributes(): array
    {
        return ['content', 'agenda_id'];
    }

    protected function activityIgnoredOnly(): array
    {
        return ['sort_order'];
    }

    protected function activityDescription(string $event): string
    {
        return match ($event) {
            'created' => 'mencatat keputusan',
            'deleted' => 'menghapus keputusan',
            default => 'mengubah keputusan',
        };
    }

    public function activityMeetingId(): ?int
    {
        return $this->meeting_id;
    }

    public function activityLabel(): ?string
    {
        return Str::limit((string) $this->content, 120);
    }
}
