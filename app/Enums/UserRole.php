<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What someone with an account may do. Organisers run everything; desk staff only use the headset desk.
 */
enum UserRole: string implements HasLabel
{
    case Organiser = 'organiser';
    case DeskStaff = 'desk_staff';

    public function getLabel(): string
    {
        return match ($this) {
            self::Organiser => 'Organiser',
            self::DeskStaff => 'Headset desk only',
        };
    }
}
