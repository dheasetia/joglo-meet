<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ManageActivityLogs;
use App\Models\ActivityLog;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/** Log aktivitas (baca saja): siapa mengubah apa dan kapan. */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $modelLabel = 'log aktivitas';

    protected static ?string $pluralModelLabel = 'Log Aktivitas';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')
                    ->dateTime('d M Y H:i')->sortable()
                    ->description(fn (ActivityLog $record) => $record->created_at->diffForHumans()),
                TextColumn::make('causer_id')->label('Pengguna')
                    ->formatStateUsing(fn (ActivityLog $record) => $record->causerName())
                    ->placeholder('Sistem'),
                TextColumn::make('log_name')->label('Kelompok')->badge()
                    ->formatStateUsing(fn (ActivityLog $record) => $record->logLabel())
                    ->color(fn (?string $state) => match ($state) {
                        'rapat' => 'info',
                        'notulen' => 'primary',
                        'rtl' => 'warning',
                        'presensi' => 'success',
                        'akses' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('description')->label('Aktivitas')->wrap()->searchable()
                    ->formatStateUsing(fn (ActivityLog $record) => $record->description
                        .($record->subjectLabel() ? ' “'.$record->subjectLabel().'”' : '')),
                TextColumn::make('properties')->label('Rapat')
                    ->state(fn (ActivityLog $record) => $record->getProperty('meeting_id') ? 'Buka rapat' : null)
                    ->url(fn (ActivityLog $record) => $record->getProperty('meeting_id') ? route('meetings.show', $record->getProperty('meeting_id')) : null)
                    ->color('primary')
                    ->placeholder('—'),
                TextColumn::make('attribute_changes')->label('Perubahan')->wrap()
                    ->state(fn (ActivityLog $record) => self::changesHtml($record))
                    ->html()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('log_name')->label('Kelompok')->options(ActivityLog::LOG_NAMES),
                SelectFilter::make('causer')->label('Pengguna')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $data['value'])
                        : $query),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['dari'] ?? null) ? 'Dari '.Carbon::parse($data['dari'])->translatedFormat('d M Y') : null,
                        ($data['sampai'] ?? null) ? 'Sampai '.Carbon::parse($data['sampai'])->translatedFormat('d M Y') : null,
                    ])),
            ])
            ->recordActions([])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Belum ada aktivitas')
            ->emptyStateDescription('Perubahan pada rapat, notulen, RTL, presensi, dan data master akan tercatat di sini.');
    }

    /** Ringkasan perubahan "Kolom: lama → baru" sebagai HTML yang sudah di-escape. */
    protected static function changesHtml(ActivityLog $record): ?HtmlString
    {
        $lines = $record->changeLines();
        if (! $lines) {
            return null;
        }

        $html = collect($lines)->take(8)->map(function (array $line) {
            $field = '<span style="font-weight:600">'.e($line['field']).':</span> ';

            return $field.match (true) {
                $line['old'] !== null && $line['new'] !== null => '<span style="text-decoration:line-through;opacity:.6">'.e($line['old']).'</span> → '.e($line['new']),
                $line['new'] !== null => e($line['new']),
                default => '<span style="text-decoration:line-through;opacity:.6">'.e($line['old']).'</span>',
            };
        })->implode('<br>');

        if (count($lines) > 8) {
            $html .= '<br><span style="opacity:.6">… dan '.(count($lines) - 8).' perubahan lain</span>';
        }

        return new HtmlString('<span style="font-size:.8rem;line-height:1.4">'.$html.'</span>');
    }

    public static function getPages(): array
    {
        return ['index' => ManageActivityLogs::route('/')];
    }
}
