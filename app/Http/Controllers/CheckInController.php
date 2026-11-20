<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Presensi mandiri: peserta memindai QR rapat dari HP-nya. */
class CheckInController extends Controller
{
    public function __invoke(Request $request, string $token): View
    {
        $meeting = Meeting::where('checkin_token', $token)->firstOrFail();
        $user = $request->user();

        if (! in_array($meeting->status, [MeetingStatus::Terjadwal, MeetingStatus::Berlangsung], true)) {
            return view('meetings.checkin', ['meeting' => $meeting, 'ok' => false, 'message' => 'Presensi untuk rapat ini sudah ditutup.']);
        }

        if (! $meeting->date->isToday()) {
            return view('meetings.checkin', ['meeting' => $meeting, 'ok' => false, 'message' => 'Presensi hanya bisa dilakukan pada hari rapat ('.$meeting->date->translatedFormat('l, d F Y').').']);
        }

        $participant = $meeting->participants()->where('user_id', $user->id)->first();

        if (! $participant) {
            if ($meeting->isConfidential()) {
                return view('meetings.checkin', ['meeting' => $meeting, 'ok' => false, 'message' => 'Anda tidak terdaftar sebagai peserta rapat tertutup ini.']);
            }
            $participant = $meeting->participants()->create(['user_id' => $user->id, 'note' => 'Presensi mandiri (tidak diundang)']);
        }

        if (! $participant->attendance_status->isPresent()) {
            $late = now()->gt($meeting->startsAt()->addMinutes(config('rapat.late_after_minutes')));
            $participant->update([
                'attendance_status' => $late ? AttendanceStatus::Terlambat : AttendanceStatus::Hadir,
                'checked_in_at' => now(),
            ]);
        }

        return view('meetings.checkin', [
            'meeting' => $meeting,
            'ok' => true,
            'message' => 'Presensi tercatat: '.$participant->attendance_status->label().' pukul '.$participant->checked_in_at->format('H:i').'.',
        ]);
    }
}
