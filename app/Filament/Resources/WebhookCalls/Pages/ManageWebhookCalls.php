<?php

namespace App\Filament\Resources\WebhookCalls\Pages;

use App\Filament\Resources\WebhookCalls\WebhookCallResource;
use Filament\Resources\Pages\ManageRecords;

class ManageWebhookCalls extends ManageRecords
{
    protected static string $resource = WebhookCallResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
