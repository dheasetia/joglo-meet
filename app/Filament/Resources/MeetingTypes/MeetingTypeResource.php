<?php

namespace App\Filament\Resources\MeetingTypes;

use App\Filament\Resources\MeetingTypes\Pages\ManageMeetingTypes;
use App\Models\MeetingType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MeetingTypeResource extends Resource
{
    protected static ?string $model = MeetingType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $modelLabel = 'jenis rapat';

    protected static ?string $pluralModelLabel = 'Jenis Rapat';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama jenis rapat')->required()->maxLength(255),
            Select::make('unit_id')->label('Unit')->relationship('unit', 'name')->preload(),
            TextInput::make('description')->label('Keterangan')->maxLength(255)->columnSpanFull(),
            TagsInput::make('default_agendas')
                ->label('Template agenda inti')
                ->placeholder('Ketik agenda lalu Enter')
                ->helperText('Agenda pembuka, evaluasi RTL, “Lain-lain” dan penutup ditambahkan otomatis.')
                ->columnSpanFull(),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('unit.name')->label('Unit'),
                TextColumn::make('default_agendas')->label('Template agenda')->badge(),
                TextColumn::make('meetings_count')->counts('meetings')->label('Jumlah rapat'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMeetingTypes::route('/')];
    }
}
