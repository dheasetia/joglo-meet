<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingType extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'description', 'default_agendas', 'unit_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'default_agendas' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    // ---------- Log aktivitas ----------

    public static function activityLogName(): string
    {
        return 'data-master';
    }

    protected function activityAttributes(): array
    {
        return ['name', 'description', 'default_agendas', 'unit_id', 'is_active'];
    }

    protected function activityDescription(string $event): string
    {
        return match ($event) {
            'created' => 'menambahkan jenis rapat',
            'deleted' => 'menghapus jenis rapat',
            default => 'mengubah jenis rapat',
        };
    }

    public function activityLabel(): ?string
    {
        return $this->name;
    }
}
