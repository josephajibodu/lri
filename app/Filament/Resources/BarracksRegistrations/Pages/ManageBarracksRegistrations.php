<?php

namespace App\Filament\Resources\BarracksRegistrations\Pages;

use App\Filament\Resources\BarracksRegistrations\BarracksRegistrationResource;
use App\Models\BarracksRegistration;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageBarracksRegistrations extends ManageRecords
{
    protected static string $resource = BarracksRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printAll')
                ->label('Print all forms')
                ->icon(Heroicon::OutlinedPrinter)
                ->url(route('barracks.print'), shouldOpenInNewTab: true)
                ->visible(fn (): bool => BarracksRegistration::query()->exists()),
            Action::make('registrationForm')
                ->label('Open the registration form')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(route('barracks.create'), shouldOpenInNewTab: true),
        ];
    }
}
