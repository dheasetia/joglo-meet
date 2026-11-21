<?php

namespace App\Http\Controllers;

use App\Exports\MeetingExport;
use App\Models\Meeting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduh notulen satu rapat sebagai Excel (hak akses sama dengan melihat rapat). */
class MeetingExcelController extends Controller
{
    public function __invoke(Request $request, Meeting $meeting): StreamedResponse
    {
        abort_unless($meeting->canView($request->user()), 403);

        return MeetingExport::build($meeting)->download(MeetingExport::filename($meeting));
    }
}
