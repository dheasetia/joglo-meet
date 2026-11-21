<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingPrintController extends Controller
{
    public function __invoke(Request $request, Meeting $meeting): View
    {
        abort_unless($meeting->canView($request->user()), 403);

        $meeting->load([
            'type', 'unit', 'chair', 'secretary', 'approver',
            'participants.user.unit',
            'agendas.presenter',
            'agendas.attachments',
            'agendas.decisions.actionItems.pic',
            'actionItems.pic',
        ]);

        return view('meetings.print', [
            'meeting' => $meeting,
            'carryOver' => $meeting->carryOverActionItems(),
        ]);
    }
}
