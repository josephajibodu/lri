<?php

namespace App\Filament\Resources\Birthdays\Pages;

use App\Filament\Resources\Birthdays\BirthdayResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageBirthdays extends ManageRecords
{
    protected static string $resource = BirthdayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('shareForm')
                ->label('Open the share form')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(route('birthdays.create'), shouldOpenInNewTab: true),
        ];
    }
}
