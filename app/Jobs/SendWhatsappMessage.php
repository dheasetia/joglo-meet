<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Services\WhatsApp\Gateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Kirim satu pesan dari kotak keluar. Gagal sementara dicoba ulang; gagal permanen langsung ditandai. */
class SendWhatsappMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Jeda sebelum percobaan ulang: 1 menit, lalu 5 menit. */
    public array $backoff = [60, 300];

    public function __construct(public int $messageId) {}

    public function handle(Gateway $gateway): void
    {
        $message = WhatsappMessage::find($this->messageId);
        if (! $message || $message->status !== WhatsappMessage::QUEUED) {
            return; // sudah terkirim/dilewati, atau dihapus
        }

        $message->increment('attempts');
        $result = $gateway->send($message->phone, $message->message);

        if ($result->ok) {
            $message->update(['status' => WhatsappMessage::SENT, 'provider_id' => $result->providerId, 'sent_at' => now(), 'error' => null]);

            return;
        }

        $message->update(['error' => $result->error]);

        if (! $result->retryable || $this->attempts() >= $this->tries) {
            $message->update(['status' => WhatsappMessage::FAILED]);

            return;
        }

        $this->release($this->backoff[$this->attempts() - 1] ?? 300);
    }

    public function failed(?\Throwable $e): void
    {
        WhatsappMessage::whereKey($this->messageId)->where('status', WhatsappMessage::QUEUED)
            ->update(['status' => WhatsappMessage::FAILED, 'error' => mb_substr((string) $e?->getMessage(), 0, 500)]);
    }
}
