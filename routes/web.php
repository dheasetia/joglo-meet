<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\MeetingExcelController;
use App\Http\Controllers\MeetingPrintController;
use App\Livewire\ActionBoard;
use App\Livewire\AttendanceReport;
use App\Livewire\Dashboard;
use App\Livewire\MeetingForm;
use App\Livewire\MeetingIndex;
use App\Livewire\MeetingRoom;
use App\Livewire\MyTasks;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('/rapat', MeetingIndex::class)->name('meetings.index');
    Route::get('/rapat/buat', MeetingForm::class)->name('meetings.create');
    Route::get('/rapat/{meetingId}/ubah', MeetingForm::class)->whereNumber('meetingId')->name('meetings.edit');
    Route::get('/rapat/{meeting}', MeetingRoom::class)->whereNumber('meeting')->name('meetings.show');
    Route::get('/rapat/{meeting}/cetak', MeetingPrintController::class)->whereNumber('meeting')->name('meetings.print');
    Route::get('/rapat/{meeting}/excel', MeetingExcelController::class)->whereNumber('meeting')->name('meetings.excel');

    Route::get('/hadir/{token}', CheckInController::class)->name('checkin');

    Route::get('/lampiran/{attachment}', AttachmentController::class)->whereNumber('attachment')->name('attachments.show');

    Route::get('/rtl', ActionBoard::class)->name('actions.index');
    Route::get('/tugas-saya', MyTasks::class)->name('actions.mine');

    Route::get('/laporan/kehadiran', AttendanceReport::class)->name('reports.attendance');
});
