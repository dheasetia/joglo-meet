<?php

namespace App\Models;

use App\Enums\Role;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'jabatan', 'role', 'unit_id', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class, 'pic_id');
    }

    /** Super admin & pimpinan: melihat dan mengelola semua rapat. */
    public function isAdmin(): bool
    {
        return in_array($this->role, [Role::SuperAdmin, Role::Pimpinan], true);
    }

    public function canCreateMeeting(): bool
    {
        return $this->isAdmin() || in_array($this->role, [Role::Notulis, Role::KepalaUnit], true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->isAdmin();
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))
            ->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');
    }
}
