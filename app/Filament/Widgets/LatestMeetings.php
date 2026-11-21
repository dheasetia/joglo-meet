<?php

namespace App\Filament\Widgets;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Rapat terbaru & mendatang; klik baris untuk membuka ruang rapat di aplikasi. */
class LatestMeetings extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Rapat terbaru')
            ->query(fn () => Meeting::query()->with(['type', 'unit', 'chair'])->withCount('participants'))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Tanggal')->date('d M Y')->sortable()
                    ->description(fn (Meeting $record) => $record->timeRange()),
                TextColumn::make('title')->label('Rapat')->searchable()->weight('semibold')
                    ->description(fn (Meeting $record) => $record->type?->name ?? 'Rapat umum'),
                TextColumn::make('unit.name')->label('Unit')->placeholder('—'),
                TextColumn::make('chair.name')->label('Pimpinan rapat'),
                TextColumn::make('participants_count')->label('Peserta')->alignCenter(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (MeetingStatus $state) => $state->label())
                    ->color(fn (MeetingStatus $state) => match ($state) {
                        MeetingStatus::Terjadwal => 'info',
                        MeetingStatus::Berlangsung => 'warning',
                        MeetingStatus::Selesai => Color::Violet, // sama dengan badge di aplikasi
                        MeetingStatus::Disahkan => 'success',
                        MeetingStatus::Batal => 'gray',
                    }),
            ])
            ->recordUrl(fn (Meeting $record) => route('meetings.show', $record))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Belum ada rapat')
            ->emptyStateDescription('Rapat yang dibuat di aplikasi akan muncul di sini.');
    }
}
