<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
        return ['name', 'description'];
    }

    protected function activityDescription(string $event): string
    {
        return match ($event) {
            'created' => 'menambahkan unit',
            'deleted' => 'menghapus unit',
            default => 'mengubah unit',
        };
    }

    public function activityLabel(): ?string
    {
        return $this->name;
    }
}
