<?php

namespace App\Console\Commands;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingSignature;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Terbitkan tanda tangan digital untuk rapat yang sudah disahkan sebelum fitur QR ada.
 * Penandatangan & waktu diambil dari data pengesahan (approved_by / approved_at).
 */
class IssueMissingSignatures extends Command
{
    protected $signature = 'rapat:terbitkan-tanda-tangan {--dry-run : Hanya tampilkan rapat yang akan diproses}';

    protected $description = 'Terbitkan kode verifikasi (QR) untuk notulen disahkan yang belum memilikinya';

    public function handle(): int
    {
        $meetings = Meeting::query()
            ->where('status', MeetingStatus::Disahkan->value)
            ->whereDoesntHave('signatures', fn ($q) => $q->whereNull('revoked_at'))
            ->orderBy('date')
            ->get();

        if ($meetings->isEmpty()) {
            $this->info('Semua notulen yang disahkan sudah memiliki tanda tangan digital.');

            return self::SUCCESS;
        }

        foreach ($meetings as $meeting) {
            $signer = User::find($meeting->approved_by) ?? User::find($meeting->chair_id);

            if ($this->option('dry-run')) {
                $this->line("• {$meeting->date->format('Y-m-d')} {$meeting->title} (disahkan oleh {$signer?->name})");

                continue;
            }

            $signature = MeetingSignature::issue($meeting, $signer);
            $signature->update(['signed_at' => $meeting->approved_at ?? $signature->signed_at]);
            $this->line("✓ {$meeting->title}: {$signature->code}");
        }

        $this->info($this->option('dry-run') ? $meetings->count().' rapat akan diproses.' : $meetings->count().' tanda tangan diterbitkan.');

        return self::SUCCESS;
    }
}
