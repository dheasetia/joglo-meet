<?php

namespace App\Services\WhatsApp;

final class SendResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $providerId = null,
        public readonly ?string $error = null,
        /** false = gagal permanen (mis. token/nomor salah), tidak perlu dicoba ulang */
        public readonly bool $retryable = true,
    ) {}
}
