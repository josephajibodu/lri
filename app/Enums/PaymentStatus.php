<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where one payment attempt stands, in our own words. Each provider translates its statuses into these.
 */
enum PaymentStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Abandoned = 'abandoned';
    case Expired = 'expired';
    case Underpaid = 'underpaid';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    /**
     * The status as organisers read it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Abandoned => 'Abandoned',
            self::Expired => 'Expired',
            self::Underpaid => 'Underpaid',
            self::Refunded => 'Refunded',
            self::PartiallyRefunded => 'Partly refunded',
        };
    }

    /**
     * Whether the attempt can still be paid, so it is worth asking the provider about it.
     */
    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Succeeded => 'success',
            self::Failed, self::Underpaid => 'danger',
            self::Abandoned, self::Expired => 'gray',
            self::Refunded, self::PartiallyRefunded => 'info',
        };
    }
}
