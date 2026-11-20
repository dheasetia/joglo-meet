<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Decision extends Model
{
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
}
