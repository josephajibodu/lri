<?php

namespace App\Filament\Resources\TicketTypes\Pages;

use App\Filament\Resources\TicketTypes\TicketTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketType extends CreateRecord
{
    protected static string $resource = TicketTypeResource::class;
}
