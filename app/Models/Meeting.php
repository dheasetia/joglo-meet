<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Enums\ActionStatus;
use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Meeting extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'meeting_type_id', 'meeting_schedule_id', 'unit_id', 'title', 'date', 'start_time', 'end_time',
        'location', 'online_link', 'chair_id', 'secretary_id', 'confidentiality',
        'status', 'notes', 'started_at', 'ended_at', 'approved_at', 'approved_by', 'created_by',
    ];

    /** Samakan dengan default kolom di migrasi. */
    protected $attributes = [
        'status' => 'terjadwal',
        'confidentiality' => 'biasa',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => MeetingStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Meeting $meeting) {
            $meeting->checkin_token ??= Str::random(40);
        });
    }

    // ---------- Relasi ----------

    public function type(): BelongsTo
    {
        return $this->belongsTo(MeetingType::class, 'meeting_type_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Jadwal rutin asal rapat (bila dibuat otomatis). */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MeetingSchedule::class, 'meeting_schedule_id');
    }

    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chair_id');
    }

    public function secretary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function agendas(): HasMany
    {
        return $this->hasMany(Agenda::class)->orderBy('sort_order');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class)->orderBy('sort_order');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(MeetingSignature::class)->latest('id');
    }

    /** Tanda tangan digital yang masih berlaku (belum dicabut karena buka kunci). */
    public function activeSignature(): HasOne
    {
        return $this->hasOne(MeetingSignature::class)->whereNull('revoked_at')->latestOfMany();
    }

    /**
     * Sidik jari (SHA-256) isi notulen untuk tanda tangan digital: data rapat, agenda & pembahasan,
     * keputusan, RTL (uraian, PIC, deadline — bukan progres yang memang terus berubah), daftar hadir,
     * dan lampiran agenda. Disusun kanonik (urutan & tipe tetap) agar hash stabil.
     */
    public function contentFingerprint(): string
    {
        $time = fn ($t) => $t ? substr((string) $t, 0, 5) : null;

        $payload = [
            'rapat' => [
                'id' => $this->id,
                'judul' => $this->title,
                'tanggal' => $this->date?->format('Y-m-d'),
                'mulai' => $time($this->start_time),
                'selesai' => $time($this->end_time),
                'tempat' => $this->location,
                'tautan' => $this->online_link,
                'pimpinan' => $this->chair_id,
                'notulis' => $this->secretary_id,
                'sifat' => $this->confidentiality,
                'catatan' => $this->notes,
            ],
            'agenda' => Agenda::where('meeting_id', $this->id)->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'sort_order', 'title', 'presenter_id', 'duration_minutes', 'discussion'])
                ->map(fn ($a) => [$a->id, $a->sort_order, $a->title, $a->presenter_id, $a->duration_minutes, (string) $a->discussion])->all(),
            'keputusan' => Decision::where('meeting_id', $this->id)->orderBy('id')
                ->get(['id', 'agenda_id', 'content'])
                ->map(fn ($d) => [$d->id, $d->agenda_id, $d->content])->all(),
            'rtl' => ActionItem::where('meeting_id', $this->id)->orderBy('id')
                ->get(['id', 'decision_id', 'title', 'pic_id', 'due_date', 'priority', 'success_indicator'])
                ->map(fn ($i) => [$i->id, $i->decision_id, $i->title, $i->pic_id, $i->due_date?->format('Y-m-d'), $i->priority?->value, $i->success_indicator])->all(),
            'hadir' => MeetingParticipant::where('meeting_id', $this->id)->orderBy('id')
                ->get(['id', 'user_id', 'guest_name', 'attendance_status', 'note'])
                ->map(fn ($p) => [$p->id, $p->user_id, $p->guest_name, $p->attendance_status?->value, $p->note])->all(),
            'lampiran' => Attachment::where('meeting_id', $this->id)->where('attachable_type', (new Agenda)->getMorphClass())->orderBy('id')
                ->get(['id', 'original_name', 'size'])
                ->map(fn ($f) => [$f->id, $f->original_name, $f->size])->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    // ---------- Hak akses ----------

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('chair_id', $user->id)
                ->orWhere('secretary_id', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhereHas('participants', fn (Builder $p) => $p->where('user_id', $user->id));
        });
    }

    public function canManage(User $user): bool
    {
        return $user->isAdmin()
            || in_array($user->id, [$this->chair_id, $this->secretary_id, $this->created_by], true);
    }

    public function canView(User $user): bool
    {
        return $this->canManage($user)
            || $this->participants()->where('user_id', $user->id)->exists();
    }

    public function canApprove(User $user): bool
    {
        return $user->isAdmin() || $user->id === $this->chair_id;
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [MeetingStatus::Disahkan, MeetingStatus::Batal], true);
    }

    public function isConfidential(): bool
    {
        return $this->confidentiality === 'rahasia';
    }

    // ---------- Bantuan ----------

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d').' '.$this->start_time);
    }

    public function timeRange(): string
    {
        $range = substr($this->start_time, 0, 5);

        return $this->end_time ? $range.' – '.substr($this->end_time, 0, 5) : $range.' – selesai';
    }

    /**
     * RTL dari rapat-rapat sebelumnya (jenis rapat yang sama, atau unit yang sama bila tanpa jenis)
     * yang masih terbuka, ditambah RTL yang progresnya dicatat di rapat ini.
     * Inilah isi agenda "Evaluasi RTL rapat sebelumnya".
     */
    public function carryOverActionItems(): Collection
    {
        return ActionItem::query()
            ->with([
                'pic',
                'meeting',
                'evidenceAttachments',
                'updates' => fn ($u) => $u->where('meeting_id', $this->id)->with('user'),
            ])
            ->where('meeting_id', '!=', $this->id)
            ->whereHas('meeting', function (Builder $q) {
                $q->where(function (Builder $d) {
                    $d->whereDate('date', '<', $this->date)
                        ->orWhere(fn (Builder $same) => $same->whereDate('date', $this->date)->where('id', '<', $this->id));
                });

                if ($this->meeting_type_id) {
                    $q->where('meeting_type_id', $this->meeting_type_id);
                } elseif ($this->unit_id) {
                    $q->whereNull('meeting_type_id')->where('unit_id', $this->unit_id);
                } else {
                    $q->whereNull('meeting_type_id')->whereNull('unit_id');
                }
            })
            ->where(function (Builder $q) {
                $q->whereIn('status', array_map(fn ($s) => $s->value, ActionStatus::openCases()))
                    ->orWhereHas('updates', fn (Builder $u) => $u->where('meeting_id', $this->id));
            })
            ->orderBy('due_date')
            ->get();
    }

    /** @return array{total:int, present:int, percent:int} */
    public function attendanceSummary(): array
    {
        $participants = $this->relationLoaded('participants') ? $this->participants : $this->participants()->get();
        $total = $participants->count();
        $present = $participants->filter(fn ($p) => $p->attendance_status->isPresent())->count();

        return [
            'total' => $total,
            'present' => $present,
            'percent' => $total ? (int) round($present / $total * 100) : 0,
        ];
    }

    // ---------- Log aktivitas ----------

    public static function activityLogName(): string
    {
        return 'rapat';
    }

    protected function activityAttributes(): array
    {
        return ['title', 'meeting_type_id', 'unit_id', 'date', 'start_time', 'end_time', 'location',
            'online_link', 'chair_id', 'secretary_id', 'confidentiality', 'status'];
    }

    /** Catatan umum tersimpan otomatis saat diketik; dicatat terpisah (dibatasi) oleh Ruang Rapat. */
    protected function activityIgnoredOnly(): array
    {
        return ['notes', 'started_at', 'ended_at', 'approved_at', 'approved_by', 'checkin_token'];
    }

    protected function activityDescription(string $event): string
    {
        if ($event === 'created') {
            return 'membuat rapat';
        }

        if ($event === 'deleted') {
            return 'menghapus rapat';
        }

        if (! $this->wasChanged('status')) {
            return 'mengubah data rapat';
        }

        return match ($this->status) {
            MeetingStatus::Berlangsung => 'memulai rapat',
            MeetingStatus::Selesai => $this->getOriginal('status') === MeetingStatus::Disahkan ? 'membuka kunci notulen' : 'mengakhiri rapat',
            MeetingStatus::Disahkan => 'mengesahkan notulen',
            MeetingStatus::Batal => 'membatalkan rapat',
            MeetingStatus::Terjadwal => 'menjadwalkan ulang rapat',
        };
    }

    public function activityMeetingId(): ?int
    {
        return $this->id;
    }

    public function activityLabel(): ?string
    {
        return $this->title;
    }
}
