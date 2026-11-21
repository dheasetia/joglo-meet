<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Membuka/mengunduh lampiran privat setelah mengecek hak akses (rapat rahasia ikut terlindungi). */
class AttachmentController extends Controller
{
    public function __invoke(Request $request, Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->canView($request->user()), 403);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404, 'Berkas tidak ditemukan.');

        $headers = [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ];

        // Foto & PDF dibuka di browser; lainnya (atau ?unduh=1) diunduh dengan nama aslinya
        $disposition = ($attachment->opensInline() && ! $request->boolean('unduh')) ? 'inline' : 'attachment';

        return $disk->response($attachment->path, $attachment->original_name, $headers, $disposition);
    }
}
