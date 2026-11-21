<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Mode uji coba: pesan hanya dicatat ke log, tidak dikirim. */
class LogGateway implements Gateway
{
    public function send(string $phone, string $message): SendResult
    {
        Log::info("[WhatsApp:log] ke {$phone}\n{$message}");

        return new SendResult(true, 'log-'.Str::random(10));
    }
}
