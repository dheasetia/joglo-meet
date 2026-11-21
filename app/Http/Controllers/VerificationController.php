<?php

namespace App\Http\Controllers;

use App\Models\MeetingSignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman publik verifikasi tanda tangan digital notulen (tanpa login).
 * Hanya menyatakan keabsahan; isi notulen tidak pernah ditampilkan.
 */
class VerificationController extends Controller
{
    /** Form isi kode manual (bila QR tidak bisa dipindai). */
    public function index(Request $request): View|RedirectResponse
    {
        if ($code = trim((string) $request->query('kode'))) {
            return redirect()->route('verification.show', MeetingSignature::normalizeCode($code));
        }

        return view('verification.show', ['state' => 'form', 'code' => null, 'signature' => null]);
    }

    public function show(string $code): View
    {
        $code = MeetingSignature::normalizeCode($code);
        $signature = MeetingSignature::with(['meeting.type', 'meeting.unit', 'meeting.chair', 'meeting.secretary', 'signer', 'revoker'])
            ->where('code', $code)->first();

        $state = match (true) {
            ! $signature || ! $signature->meeting => 'tidak-ditemukan',
            $signature->isRevoked() => 'dicabut',
            // Isi notulen di sistem berbeda dari saat disahkan
            ! hash_equals($signature->content_hash, $signature->meeting->contentFingerprint()) => 'berubah',
            default => 'sah',
        };

        return view('verification.show', [
            'state' => $state,
            'code' => $code,
            'signature' => $signature,
            'newer' => $state === 'dicabut' ? $signature->meeting?->activeSignature : null,
        ]);
    }
}
