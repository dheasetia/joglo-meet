<?php

namespace App\Filament\Widgets;

use App\Enums\MeetingStatus;
use App\Filament\Resources\MeetingTypes\MeetingTypeResource;
use App\Filament\Resources\Units\UnitResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\MeetingType;
use App\Models\Unit;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ringkasan data master & aktivitas rapat di dashboard /admin. */
class MasterDataStats extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Ringkasan';

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $activeUsers = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();
        $usersWithoutUnit = User::whereNull('unit_id')->count();

        $monthMeetings = Meeting::query()
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
            ->where('status', '!=', MeetingStatus::Batal->value);
        $pendingApproval = Meeting::where('status', MeetingStatus::Selesai->value)->count();

        $overdue = ActionItem::query()->overdue()->count();
        $open = ActionItem::query()->open()->count();

        return [
            Stat::make('Pengguna aktif', $activeUsers)
                ->description($inactiveUsers ? "{$inactiveUsers} nonaktif" : 'Semua akun aktif')
                ->descriptionIcon('heroicon-m-users')
                ->color($inactiveUsers ? 'warning' : 'success')
                ->url(UserResource::getUrl('index')),

            Stat::make('Unit / Divisi', Unit::count())
                ->description($usersWithoutUnit ? "{$usersWithoutUnit} pengguna belum ber-unit" : 'Semua pengguna ber-unit')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color($usersWithoutUnit ? 'warning' : 'gray')
                ->url(UnitResource::getUrl('index')),

            Stat::make('Jenis rapat aktif', MeetingType::where('is_active', true)->count())
                ->description(MeetingType::count().' jenis terdaftar')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('gray')
                ->url(MeetingTypeResource::getUrl('index')),

            Stat::make('Rapat bulan ini', (clone $monthMeetings)->count())
                ->description($pendingApproval ? "{$pendingApproval} menunggu pengesahan" : 'Tidak ada yang menunggu pengesahan')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($pendingApproval ? 'warning' : 'success')
                ->url(route('meetings.index')),

            Stat::make('RTL terlambat', $overdue)
                ->description("{$open} RTL masih berjalan")
                ->descriptionIcon($overdue ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdue ? 'danger' : 'success')
                ->url(route('actions.index', ['filter' => 'terlambat'])),
        ];
    }
}
