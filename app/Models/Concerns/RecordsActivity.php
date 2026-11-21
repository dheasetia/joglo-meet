<?php

namespace App\Models\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Pencatatan log aktivitas otomatis (spatie/laravel-activitylog) dengan kalimat
 * berbahasa Indonesia dan meeting_id di properties agar bisa difilter per rapat.
 *
 * Model yang memakai trait ini mengisi:
 *  - activityLogName(): kelompok log (rapat, notulen, rtl, presensi, data-master)
 *  - activityAttributes(): kolom yang dicatat perubahannya
 *  - activityIgnoredOnly(): kolom yang bila HANYA kolom ini berubah, tidak dicatat
 *  - activityDescription($event): kalimat aktivitas, mis. "mencatat keputusan"
 *  - activityMeetingId(): rapat terkait (opsional)
 *  - activityLabel(): nama objek saat itu, disimpan agar tetap terbaca walau objek dihapus
 */
trait RecordsActivity
{
    use LogsActivity;

    /** Bila true, event model kelas ini tidak dicatat (dipakai untuk operasi massal yang diringkas). */
    protected static bool $activityMuted = false;

    abstract public static function activityLogName(): string;

    abstract protected function activityAttributes(): array;

    abstract protected function activityDescription(string $event): string;

    protected function activityIgnoredOnly(): array
    {
        return [];
    }

    public function activityMeetingId(): ?int
    {
        return null;
    }

    public function activityLabel(): ?string
    {
        return null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(static::activityLogName())
            ->logOnly($this->activityAttributes())
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->dontLogIfAttributesChangedOnly([...$this->activityIgnoredOnly(), 'created_at', 'updated_at'])
            // String kosong = jangan catat (dipakai saat dibisukan)
            ->setDescriptionForEvent(fn (string $event) => static::$activityMuted ? '' : $this->activityDescription($event));
    }

    /** Dipanggil spatie sebelum log disimpan: tambahkan meeting_id & label objek. */
    public function beforeActivityLogged(Model $activity, string $eventName): void
    {
        $activity->properties = collect($activity->properties ?? [])->merge(array_filter([
            'meeting_id' => $this->activityMeetingId(),
            'label' => $this->activityLabel(),
        ], fn ($value) => $value !== null && $value !== ''));
    }

    /** Jalankan $callback tanpa mencatat event model kelas ini (ringkasannya dicatat manual). */
    public static function withoutActivity(Closure $callback): mixed
    {
        $previous = static::$activityMuted;
        static::$activityMuted = true;

        try {
            return $callback();
        } finally {
            static::$activityMuted = $previous;
        }
    }
}
