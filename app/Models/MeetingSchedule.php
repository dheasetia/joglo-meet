<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use App\Enums\Role;
use App\Enums\ScheduleFrequency;
use App\Models\Concerns\RecordsActivity;
use App\Support\AgendaTemplate;
use App\Support\MeetingActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Jadwal rapat rutin. Setiap pagi (rapat:buat-dari-jadwal) rapat yang jatuh dalam
 * `lead_days` hari ke depan dibuat otomatis dari jadwal ini.
 */
class MeetingSchedule extends Model
{
    use RecordsActivity;

    /** Nama hari ISO (1 = Senin … 7 = Ahad). */
    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Ahad'];

    public const WEEKS = [1 => 'pekan ke-1', 2 => 'pekan ke-2', 3 => 'pekan ke-3', 4 => 'pekan ke-4', 5 => 'pekan terakhir'];

    protected $fillable = [
        'title', 'meeting_type_id', 'unit_id', 'frequency', 'day_of_week', 'week_of_month',
        'start_time', 'end_time', 'location', 'online_link', 'chair_id', 'secretary_id',
        'confidentiality', 'participant_ids', 'starts_on', 'ends_on', 'lead_days', 'is_active', 'created_by',
    ];

    protected $attributes = [
        'confidentiality' => 'biasa',
        'lead_days' => 7,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'frequency' => ScheduleFrequency::class,
            'day_of_week' => 'integer',
            'week_of_month' => 'integer',
            'participant_ids' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'lead_days' => 'integer',
            'is_active' => 'boolean',
        ];
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

    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chair_id');
    }

    public function secretary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    // ---------- Hak akses ----------

    /** Admin/pimpinan, pembuat, pimpinan rapat & notulis jadwal, atau kepala unit yang sama. */
    public function canManage(User $user): bool
    {
        return $user->isAdmin()
            || in_array($user->id, [$this->created_by, $this->chair_id, $this->secretary_id], true)
            || ($user->role === Role::KepalaUnit && $this->unit_id && $this->unit_id === $user->unit_id);
    }

    public function scopeManageableBy(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('chair_id', $user->id)
                ->orWhere('secretary_id', $user->id)
                ->when($user->role === Role::KepalaUnit && $user->unit_id, fn ($w) => $w->orWhere('unit_id', $user->unit_id));
        });
    }

    // ---------- Pola jadwal ----------

    /** Mis. "Setiap Sabtu, 13:00–14:30" atau "Senin pekan ke-1 setiap bulan, 08:00". */
    public function describe(bool $withTime = true): string
    {
        $day = self::DAYS[$this->day_of_week] ?? '?';

        $pattern = match ($this->frequency) {
            ScheduleFrequency::Mingguan => "Setiap {$day}",
            ScheduleFrequency::DuaMingguan => "Dua pekan sekali, hari {$day}",
            ScheduleFrequency::Bulanan => $this->week_of_month === 5
                ? "{$day} terakhir setiap bulan"
                : "{$day} ".(self::WEEKS[$this->week_of_month] ?? 'pekan ke-1').' setiap bulan',
        };

        if (! $withTime) {
            return $pattern;
        }

        $time = substr((string) $this->start_time, 0, 5).($this->end_time ? '–'.substr((string) $this->end_time, 0, 5) : '');

        return "{$pattern}, {$time}";
    }

    /**
     * Tanggal-tanggal rapat dalam rentang [from, to] (sudah dibatasi masa berlaku jadwal).
     *
     * @return Collection<int, Carbon>
     */
    public function occurrencesBetween(Carbon $from, Carbon $to): Collection
    {
        $start = $from->copy()->startOfDay()->max($this->starts_on->copy()->startOfDay());
        $end = $to->copy()->startOfDay();
        if ($this->ends_on) {
            $end = $end->min($this->ends_on->copy()->startOfDay());
        }

        $dates = collect();
        if ($start->gt($end)) {
            return $dates;
        }

        if ($this->frequency === ScheduleFrequency::Bulanan) {
            for ($month = $start->copy()->startOfMonth(); $month->lte($end); $month->addMonthNoOverflow()) {
                $date = $this->weekdayOfMonth($month);
                if ($date->betweenIncluded($start, $end)) {
                    $dates->push($date);
                }
            }

            return $dates;
        }

        // Mingguan / dua mingguan: dua mingguan berpatokan pada kemunculan pertama sejak tanggal mulai
        $step = $this->frequency === ScheduleFrequency::DuaMingguan ? 14 : 7;
        $date = $this->nextWeekday($this->frequency === ScheduleFrequency::DuaMingguan ? $this->starts_on->copy()->startOfDay() : $start);
        while ($date->lt($start)) {
            $date->addDays($step);
        }
        for (; $date->lte($end); $date = $date->copy()->addDays($step)) {
            $dates->push($date->copy());
        }

        return $dates;
    }

    /** Rapat berikutnya dalam satu tahun ke depan (hari ini hanya bila jam mulainya belum lewat). */
    public function nextOccurrence(?Carbon $after = null): ?Carbon
    {
        $after ??= $this->firstSchedulableDay();

        return $this->occurrencesBetween($after, $after->copy()->addYear())->first();
    }

    /** Hari ini bila jam mulai rapat belum lewat, selain itu besok (rapat yang jamnya lewat tidak dibuat). */
    public function firstSchedulableDay(?Carbon $now = null): Carbon
    {
        $now ??= now();
        $start = substr((string) ($this->start_time ?: '00:00'), 0, 5);

        return $now->format('H:i') < $start ? $now->copy()->startOfDay() : $now->copy()->addDay()->startOfDay();
    }

    protected function nextWeekday(Carbon $from): Carbon
    {
        $date = $from->copy()->startOfDay();
        while ($date->dayOfWeekIso !== $this->day_of_week) {
            $date->addDay();
        }

        return $date;
    }

    /** Hari `day_of_week` pekan ke-n (1–4) atau terakhir (5) pada bulan $month. */
    protected function weekdayOfMonth(Carbon $month): Carbon
    {
        if ($this->week_of_month === 5) {
            $date = $month->copy()->endOfMonth()->startOfDay();
            while ($date->dayOfWeekIso !== $this->day_of_week) {
                $date->subDay();
            }

            return $date;
        }

        return $this->nextWeekday($month->copy()->startOfMonth())->addWeeks(max(1, (int) $this->week_of_month) - 1);
    }

    // ---------- Pembuatan rapat ----------

    /**
     * Buat rapat untuk tanggal-tanggal dalam `lead_days` hari ke depan yang belum ada.
     * Rapat yang pernah dibuat (termasuk yang dibatalkan) tidak dibuat ulang.
     *
     * @return Collection<int, Meeting>
     */
    public function generateUpcoming(?Carbon $today = null): Collection
    {
        if (! $this->is_active) {
            return collect();
        }

        $today ??= $this->firstSchedulableDay();
        $this->loadMissing('type');
        $existing = $this->meetings()->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();

        return $this->occurrencesBetween($today, $today->copy()->addDays($this->lead_days))
            ->reject(fn (Carbon $date) => in_array($date->toDateString(), $existing, true))
            ->map(fn (Carbon $date) => $this->generateMeeting($date))
            ->filter()
            ->values();
    }

    public function generateMeeting(Carbon $date): ?Meeting
    {
        try {
            return DB::transaction(function () use ($date) {
                $meeting = Meeting::create([
                    'meeting_schedule_id' => $this->id,
                    'meeting_type_id' => $this->meeting_type_id,
                    'unit_id' => $this->unit_id,
                    'title' => $this->title.' · '.self::DAYS[$date->dayOfWeekIso].', '.$date->translatedFormat('d M Y'),
                    'date' => $date->toDateString(),
                    'start_time' => substr((string) $this->start_time, 0, 5),
                    'end_time' => $this->end_time ? substr((string) $this->end_time, 0, 5) : null,
                    'location' => $this->location,
                    'online_link' => $this->online_link,
                    'chair_id' => $this->chair_id,
                    'secretary_id' => $this->secretary_id,
                    'confidentiality' => $this->confidentiality,
                    'status' => MeetingStatus::Terjadwal,
                    'created_by' => $this->created_by,
                ]);

                // Agenda & peserta dicatat sebagai satu ringkasan, bukan satu log per baris
                $agendas = AgendaTemplate::for($this->type, withBlankCore: false);
                Agenda::withoutActivity(function () use ($meeting, $agendas) {
                    foreach ($agendas as $i => $agenda) {
                        $meeting->agendas()->create(['sort_order' => $i + 1, ...$agenda]);
                    }
                });

                $userIds = User::query()->where('is_active', true)
                    ->whereIn('id', collect($this->participant_ids ?? [])->push($this->chair_id, $this->secretary_id)->filter()->unique())
                    ->pluck('id');
                MeetingParticipant::withoutActivity(function () use ($meeting, $userIds) {
                    foreach ($userIds as $id) {
                        $meeting->participants()->create(['user_id' => $id]);
                    }
                });

                MeetingActivity::log($meeting, 'rapat', 'menjadwalkan rapat otomatis dari jadwal rutin', [
                    'Jadwal' => $this->title.' ('.$this->describe(false).')',
                    'Peserta' => $userIds->count().' orang',
                    'Agenda' => count($agendas).' agenda',
                ]);

                return $meeting;
            });
        } catch (UniqueConstraintViolationException) {
            return null; // sudah dibuat proses lain pada saat bersamaan
        }
    }

    // ---------- Log aktivitas ----------

    public static function activityLogName(): string
    {
        return 'rapat';
    }

    protected function activityAttributes(): array
    {
        return ['title', 'meeting_type_id', 'unit_id', 'frequency', 'day_of_week', 'week_of_month', 'start_time', 'end_time',
            'location', 'chair_id', 'secretary_id', 'confidentiality', 'starts_on', 'ends_on', 'lead_days', 'is_active'];
    }

    protected function activityIgnoredOnly(): array
    {
        return ['participant_ids'];
    }

    protected function activityDescription(string $event): string
    {
        return match (true) {
            $event === 'created' => 'membuat jadwal rapat rutin',
            $event === 'deleted' => 'menghapus jadwal rapat rutin',
            $this->wasChanged('is_active') && count($this->getChanges()) <= 2 => $this->is_active ? 'mengaktifkan jadwal rapat rutin' : 'menonaktifkan jadwal rapat rutin',
            default => 'mengubah jadwal rapat rutin',
        };
    }

    public function activityLabel(): ?string
    {
        return $this->title;
    }
}
