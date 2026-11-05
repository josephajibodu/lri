<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\AdminInvitations\AdminInvitationResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdminInvitationResource::inviteAction(),
            CreateAction::make()
                ->label('Add without an invite')
                ->color('gray'),
        ];
    }
}
