<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone')->label('No. WhatsApp')->tel()->maxLength(30)
                ->placeholder('08xxxxxxxxxx')
                ->helperText('Untuk undangan & pengingat rapat via WhatsApp.')
                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                    if (filled($value) && ! \App\Services\WhatsApp\Phone::normalize($value)) {
                        $fail('Nomor WhatsApp tidak valid. Contoh: 081234567890.');
                    }
                }),
            TextInput::make('jabatan')->maxLength(255),
            Select::make('role')->label('Peran')->options(Role::options())->required()->default(Role::Peserta->value),
            Select::make('unit_id')->label('Unit')->relationship('unit', 'name')->searchable()->preload(),
            TextInput::make('password')
                ->label('Kata sandi')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Kosongkan bila tidak ingin mengubah.'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable()
                    ->description(fn (User $record) => $record->jabatan),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('unit.name')->label('Unit')->sortable(),
                TextColumn::make('role')->label('Peran')->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof Role ? $state->label() : Role::from($state)->label()),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('unit_id')->label('Unit')->relationship('unit', 'name'),
                SelectFilter::make('role')->label('Peran')->options(Role::options()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
