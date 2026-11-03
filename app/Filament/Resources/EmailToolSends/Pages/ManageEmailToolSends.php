<?php

namespace App\Filament\Resources\EmailToolSends\Pages;

use App\Filament\Resources\EmailToolSends\EmailToolSendResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEmailToolSends extends ManageRecords
{
    protected static string $resource = EmailToolSendResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
