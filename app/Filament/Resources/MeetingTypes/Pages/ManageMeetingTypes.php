<?php

namespace App\Filament\Resources\MeetingTypes\Pages;

use App\Filament\Resources\MeetingTypes\MeetingTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMeetingTypes extends ManageRecords
{
    protected static string $resource = MeetingTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
