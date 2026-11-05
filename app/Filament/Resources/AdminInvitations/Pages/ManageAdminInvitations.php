<?php

namespace App\Filament\Resources\AdminInvitations\Pages;

use App\Filament\Resources\AdminInvitations\AdminInvitationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAdminInvitations extends ManageRecords
{
    protected static string $resource = AdminInvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdminInvitationResource::inviteAction(),
        ];
    }
}
