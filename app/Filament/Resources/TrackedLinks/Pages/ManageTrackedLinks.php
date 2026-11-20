<?php

namespace App\Filament\Resources\TrackedLinks\Pages;

use App\Filament\Resources\TrackedLinks\TrackedLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageTrackedLinks extends ManageRecords
{
    protected static string $resource = TrackedLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New link')
                ->modalHeading('New tracked link')
                ->modalDescription('Make one for each place you share the tickets, so you can see which ones bring people.')
                ->modalSubmitActionLabel('Make link')
                ->modalWidth(Width::ExtraLarge)
                ->slideOver()
                ->createAnother(false)
                ->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => auth()->id()]),
        ];
    }
}
