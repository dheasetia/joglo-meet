<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agenda extends Model
{
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
}
