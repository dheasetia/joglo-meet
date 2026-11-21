<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Enums\ActionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ActionItemUpdate extends Model
{
    use RecordsActivity;

    protected $fillable = ['action_item_id', 'user_id', 'meeting_id', 'progress', 'status', 'note'];

    protected function casts(): array
    {
        return ['status' => ActionStatus::class];
    }

    public function actionItem(): BelongsTo
    {
        return $this->belongsTo(ActionItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** Bukti progres (foto/dokumen). */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->oldest('id');
    }

    // ---------- Log aktivitas ----------

    /** Hanya pencatatan progres baru yang dicatat (riwayat progres tidak diubah/dihapus). */
    protected static array $recordEvents = ['created'];

    protected ?ActionItem $activityActionItem = null;

    public static function activityLogName(): string
    {
        return 'rtl';
    }

    protected function activityAttributes(): array
    {
        return ['progress', 'status', 'note'];
    }

    protected function activityDescription(string $event): string
    {
        return 'memperbarui progres RTL';
    }

    /** Rapat tempat progres dicatat (evaluasi RTL), atau rapat asal RTL bila dicatat dari Tugas Saya. */
    public function activityMeetingId(): ?int
    {
        return $this->meeting_id ?? $this->activityActionItem()?->meeting_id;
    }

    public function activityLabel(): ?string
    {
        return $this->activityActionItem()?->title;
    }

    protected function activityActionItem(): ?ActionItem
    {
        return $this->activityActionItem ??= ActionItem::query()->select(['id', 'meeting_id', 'title'])->find($this->action_item_id);
    }
}
