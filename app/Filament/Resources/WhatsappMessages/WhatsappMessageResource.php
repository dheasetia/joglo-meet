<?php

namespace App\Filament\Resources\WhatsappMessages;

use App\Filament\Resources\WhatsappMessages\Pages\ManageWhatsappMessages;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\Phone;
use App\Services\WhatsApp\WhatsAppNotifier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Kotak keluar notifikasi WhatsApp (baca saja + kirim ulang). */
class WhatsappMessageResource extends Resource
{
    protected static ?string $model = WhatsappMessage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $modelLabel = 'pesan WhatsApp';

    protected static ?string $pluralModelLabel = 'Pesan WhatsApp';

    protected static ?int $navigationSort = 5;

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

    /** Lencana jumlah pesan gagal di menu. */
    public static function getNavigationBadge(): ?string
    {
        $failed = WhatsappMessage::where('status', WhatsappMessage::FAILED)->count();

        return $failed ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'meeting']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable()
                    ->description(fn (WhatsappMessage $r) => $r->sent_at ? 'terkirim '.$r->sent_at->format('H:i') : null),
                TextColumn::make('phone')->label('Penerima')
                    ->state(fn (WhatsappMessage $r) => $r->user?->name ?? Phone::display($r->phone))
                    ->description(fn (WhatsappMessage $r) => $r->user ? Phone::display($r->phone) : 'nomor manual'),
                TextColumn::make('type')->label('Jenis')->badge()->color('gray')
                    ->formatStateUsing(fn (WhatsappMessage $r) => $r->typeLabel()),
                TextColumn::make('meeting.title')->label('Rapat')->limit(40)->placeholder('—')
                    ->url(fn (WhatsappMessage $r) => $r->meeting_id ? route('meetings.show', $r->meeting_id) : null),
                TextColumn::make('message')->label('Isi pesan')->limit(60)->wrap()->searchable()
                    ->tooltip(fn (WhatsappMessage $r) => $r->message),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (WhatsappMessage $r) => $r->statusLabel())
                    ->color(fn (string $state) => match ($state) {
                        WhatsappMessage::SENT => 'success',
                        WhatsappMessage::FAILED => 'danger',
                        WhatsappMessage::SKIPPED => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (WhatsappMessage $r) => $r->error),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(WhatsappMessage::STATUSES),
                SelectFilter::make('type')->label('Jenis')->options(WhatsappMessage::TYPES),
            ])
            ->recordActions([
                Action::make('kirimUlang')
                    ->label('Kirim ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->button()
                    ->size('sm')
                    ->visible(fn (WhatsappMessage $r) => in_array($r->status, [WhatsappMessage::FAILED, WhatsappMessage::SKIPPED], true))
                    ->requiresConfirmation()
                    ->modalHeading('Kirim ulang pesan?')
                    ->modalDescription('Nomor diambil ulang dari data pengguna (bila sudah diperbaiki).')
                    ->action(function (WhatsappMessage $record) {
                        $ok = app(WhatsAppNotifier::class)->retry($record);
                        $ok
                            ? Notification::make()->title('Pesan diantrekan ulang')->success()->send()
                            : Notification::make()->title('Pengguna belum punya No. WhatsApp yang valid')->warning()->send();
                    }),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Belum ada pesan')
            ->emptyStateDescription('Undangan, pengingat, dan notifikasi RTL akan tercatat di sini.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageWhatsappMessages::route('/')];
    }
}
