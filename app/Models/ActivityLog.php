<?php

namespace App\Models;

use App\Enums\ActionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\MeetingStatus;
use App\Enums\Priority;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Log aktivitas (tabel activity_log) dengan bantuan tampilan berbahasa Indonesia.
 */
class ActivityLog extends Activity
{
    /** Kelompok log → label. */
    public const LOG_NAMES = [
        'rapat' => 'Rapat',
        'notulen' => 'Notulen',
        'rtl' => 'RTL',
        'presensi' => 'Presensi',
        'data-master' => 'Data master',
        'akses' => 'Akses',
    ];

    /** Nama kolom → label. Kolom yang tidak ada di sini tidak ditampilkan pada ringkasan perubahan. */
    protected const FIELD_LABELS = [
        'title' => 'Judul',
        'name' => 'Nama',
        'content' => 'Isi',
        'description' => 'Keterangan',
        'meeting_type_id' => 'Jenis rapat',
        'unit_id' => 'Unit',
        'date' => 'Tanggal',
        'start_time' => 'Jam mulai',
        'end_time' => 'Jam selesai',
        'location' => 'Tempat',
        'online_link' => 'Tautan daring',
        'chair_id' => 'Pimpinan rapat',
        'secretary_id' => 'Notulis',
        'confidentiality' => 'Sifat',
        'status' => 'Status',
        'presenter_id' => 'Penyaji',
        'duration_minutes' => 'Durasi (menit)',
        'agenda_id' => 'Agenda',
        'pic_id' => 'Penanggung jawab',
        'due_date' => 'Deadline',
        'priority' => 'Prioritas',
        'success_indicator' => 'Indikator selesai',
        'progress' => 'Progres',
        'note' => 'Catatan',
        'user_id' => 'Peserta',
        'guest_name' => 'Nama tamu',
        'guest_info' => 'Keterangan tamu',
        'attendance_status' => 'Kehadiran',
        'email' => 'Email',
        'phone' => 'No. WhatsApp',
        'jabatan' => 'Jabatan',
        'role' => 'Peran',
        'is_active' => 'Aktif',
        'default_agendas' => 'Template agenda',
        'original_name' => 'Berkas',
        'size' => 'Ukuran',
    ];

    protected const USER_FIELDS = ['chair_id', 'secretary_id', 'presenter_id', 'pic_id', 'user_id'];

    /** Cache nama per request: [tabel => [id => nama]] */
    protected static array $nameCache = [];

    public function scopeForMeeting(Builder $query, int $meetingId): Builder
    {
        return $query->where('properties->meeting_id', $meetingId);
    }

    public function logLabel(): string
    {
        return self::LOG_NAMES[$this->log_name] ?? Str::headline((string) $this->log_name);
    }

    public function causerName(): string
    {
        return $this->causer?->name ?? 'Sistem';
    }

    /** Nama objek saat aktivitas terjadi (tetap terbaca walau objeknya sudah dihapus). */
    public function subjectLabel(): ?string
    {
        // Mis. "masuk ke aplikasi": objeknya pelaku sendiri, tidak perlu diulang
        if ($this->subject_type === $this->causer_type && $this->subject_id === $this->causer_id) {
            return null;
        }

        return $this->getProperty('label');
    }

    /**
     * Ringkasan perubahan: [['field' => 'Status', 'old' => 'Terjadwal', 'new' => 'Berlangsung'], ...]
     *
     * @return array<int, array{field:string, old:?string, new:?string}>
     */
    public function changeLines(): array
    {
        $changes = $this->attribute_changes ?? collect();
        $new = (array) ($changes['attributes'] ?? []);
        $old = (array) ($changes['old'] ?? []);

        $lines = [];
        foreach (array_unique([...array_keys($new), ...array_keys($old)]) as $field) {
            if (! isset(self::FIELD_LABELS[$field])) {
                continue;
            }

            $lines[] = [
                'field' => self::FIELD_LABELS[$field],
                'old' => array_key_exists($field, $old) ? $this->formatValue($field, $old[$field]) : null,
                'new' => array_key_exists($field, $new) ? $this->formatValue($field, $new[$field]) : null,
            ];
        }

        // Ringkasan operasi massal (mis. "tandai semua hadir") disimpan di properties.detail
        foreach ((array) $this->getProperty('detail', []) as $label => $value) {
            $lines[] = ['field' => (string) $label, 'old' => null, 'new' => is_array($value) ? implode(', ', $value) : (string) $value];
        }

        return $lines;
    }

    protected function formatValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $subject = class_basename((string) $this->subject_type);

        $text = match (true) {
            $field === 'status' && $subject === 'Meeting' => MeetingStatus::tryFrom($value)?->label(),
            $field === 'status' => ActionStatus::tryFrom($value)?->label(),
            $field === 'attendance_status' => AttendanceStatus::tryFrom($value)?->label(),
            $field === 'priority' => Priority::tryFrom($value)?->label(),
            $field === 'role' => Role::tryFrom($value)?->label(),
            $field === 'confidentiality' => $value === 'rahasia' ? 'Rahasia' : 'Biasa',
            $field === 'is_active' => $value ? 'Ya' : 'Tidak',
            $field === 'progress' => $value.'%',
            $field === 'size' => Attachment::formatBytes((int) $value),
            in_array($field, self::USER_FIELDS, true) => self::nameFor('users', $value),
            $field === 'unit_id' => self::nameFor('units', $value),
            $field === 'meeting_type_id' => self::nameFor('meeting_types', $value),
            $field === 'agenda_id' => self::nameFor('agendas', $value, 'title'),
            in_array($field, ['date', 'due_date'], true) => Carbon::parse($value)->translatedFormat('d M Y'),
            in_array($field, ['start_time', 'end_time'], true) => substr((string) $value, 0, 5),
            is_array($value) => implode(', ', $value),
            default => null,
        };

        return Str::limit($text ?? (string) $value, 160);
    }

    protected static function nameFor(string $table, mixed $id, string $column = 'name'): string
    {
        if (! array_key_exists($id, self::$nameCache[$table] ?? [])) {
            self::$nameCache[$table][$id] = DB::table($table)->where('id', $id)->value($column);
        }

        return self::$nameCache[$table][$id] ?? "#{$id} (sudah dihapus)";
    }

    /** @return Collection<int, self> */
    public static function forMeetingTimeline(int $meetingId, int $limit = 100): Collection
    {
        return static::query()
            ->forMeeting($meetingId)
            ->with('causer')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
