<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Pengiriman lewat Fonnte (https://docs.fonnte.com/api-send-message/).
 * Nomor dikirim lengkap (628…) dengan countryCode "0": tanpa itu Fonnte menambahkan
 * kode negara di depan nomor yang tidak diawali 0 sehingga menjadi 62628….
 */
class FonnteGateway implements Gateway
{
    /** Alasan gagal dari Fonnte yang tidak akan berhasil walau dicoba ulang. */
    protected const PERMANENT = ['token invalid', 'target invalid', 'input invalid', 'devices must belong to an account', 'insufficient quota'];

    public function __construct(protected ?string $token, protected string $url, protected int $timeout = 20) {}

    public function send(string $phone, string $message): SendResult
    {
        if (blank($this->token)) {
            return new SendResult(false, error: 'FONNTE_TOKEN belum diisi di .env', retryable: false);
        }

        try {
            $response = Http::asForm()
                ->withHeaders(['Authorization' => $this->token])
                ->timeout($this->timeout)
                ->post($this->url, [
                    'target' => $phone,
                    'message' => $message,
                    'countryCode' => '0',
                ]);
        } catch (ConnectionException $e) {
            return new SendResult(false, error: 'Tidak dapat menghubungi Fonnte: '.$e->getMessage());
        }

        $body = $response->json() ?? [];
        // Fonnte memakai "status" (kadang "Status") bernilai true/false
        $status = $body['status'] ?? $body['Status'] ?? false;

        if ($response->successful() && $status === true) {
            $id = $body['id'] ?? null;

            return new SendResult(true, is_array($id) ? implode(',', $id) : (string) $id);
        }

        $reason = (string) ($body['reason'] ?? $body['detail'] ?? 'HTTP '.$response->status());

        return new SendResult(false, error: 'Fonnte: '.$reason, retryable: ! in_array(strtolower($reason), self::PERMANENT, true));
    }
}
