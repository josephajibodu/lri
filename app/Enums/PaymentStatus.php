<?php

namespace App\Enums;

/**
 * Where one payment attempt stands, in our own words. Each provider translates its statuses into these.
 */
enum PaymentStatus: string
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
}
