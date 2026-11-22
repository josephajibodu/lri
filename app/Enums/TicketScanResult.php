<?php

namespace App\Enums;

/**
 * What the check-in scanner says about a ticket. Only a paid ticket that hasn't been scanned in before gets someone
 * through the gate.
 */
enum TicketScanResult: string
{
    /** Paid and not used before: they're in, and the ticket now can't get anyone else in. */
    case LetIn = 'let_in';

    /** Paid and not used before, but check-in hasn't opened, so nothing was recorded. */
    case ValidPractice = 'valid_practice';

    case AlreadyLetIn = 'already_let_in';
    case NotPaid = 'not_paid';
    case Refunded = 'refunded';
    case Disputed = 'disputed';
    case TestPayment = 'test_payment';
    case NotFound = 'not_found';

    /**
     * Whether the person can go in.
     */
    public function isValid(): bool
    {
        return $this === self::LetIn || $this === self::ValidPractice;
    }

    /**
     * The big word on the scanner's screen.
     */
    public function heading(): string
    {
        return match ($this) {
            self::LetIn, self::ValidPractice => 'Valid',
            self::AlreadyLetIn => 'Already used',
            self::NotPaid => 'Not paid',
            self::Refunded => 'Refunded',
            self::Disputed => 'Payment disputed',
            self::TestPayment => 'Test ticket',
            self::NotFound => 'Not a ticket',
        };
    }

    /**
     * The result for a ticket's order, when the ticket itself hasn't been used.
     */
    public static function forOrderStatus(OrderStatus $status): self
    {
        return match ($status) {
            OrderStatus::Paid => self::LetIn,
            OrderStatus::Pending, OrderStatus::Abandoned => self::NotPaid,
            OrderStatus::Refunded => self::Refunded,
            OrderStatus::Disputed => self::Disputed,
            OrderStatus::TestPayment => self::TestPayment,
        };
    }
}
