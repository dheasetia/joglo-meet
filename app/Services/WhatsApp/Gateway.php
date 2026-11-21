<?php

namespace App\Services\WhatsApp;

interface Gateway
{
    /** @param  string  $phone  nomor 628… */
    public function send(string $phone, string $message): SendResult;
}
