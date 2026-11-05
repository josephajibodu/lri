<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where an invitation to the admin stands.
 */
enum AdminInvitationStatus: string implements HasColor, HasLabel
{
    case Waiting = 'waiting';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * The status as organisers read it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting for a reply',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Waiting => 'warning',
            self::Accepted => 'success',
            self::Declined => 'danger',
            self::Expired, self::Cancelled => 'gray',
        };
    }
}
