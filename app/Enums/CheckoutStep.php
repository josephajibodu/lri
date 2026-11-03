<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The steps of a buyer's journey from the ticket page to a paid order, recorded so organisers can see where people stop.
 */
enum CheckoutStep: string implements HasColor, HasLabel
{
    case TicketPageViewed = 'ticket_page_viewed';
    case FormStarted = 'form_started';
    case FormInvalid = 'form_invalid';
    case DetailsSubmitted = 'details_submitted';
    case MethodChosen = 'method_chosen';
    case CheckoutUnavailable = 'checkout_unavailable';
    case CheckoutOpened = 'checkout_opened';
    case CheckoutClosed = 'checkout_closed';
    case ReturnedFromCheckout = 'returned_from_checkout';
    case PaymentSucceeded = 'payment_succeeded';
    case PaymentFailed = 'payment_failed';
    case CheckoutExpired = 'checkout_expired';
    case OrderAbandoned = 'order_abandoned';
    case CheckoutResumed = 'checkout_resumed';

    /**
     * The step as organisers read it.
     */
    public function label(): string
    {
        return match ($this) {
            self::TicketPageViewed => 'Viewed the ticket page',
            self::FormStarted => 'Started the ticket form',
            self::FormInvalid => 'Sent the form with something to fix',
            self::DetailsSubmitted => 'Submitted their details',
            self::MethodChosen => 'Chose a payment method',
            self::CheckoutUnavailable => 'Checkout couldn’t open',
            self::CheckoutOpened => 'Opened the checkout',
            self::CheckoutClosed => 'Closed the checkout',
            self::ReturnedFromCheckout => 'Came back from the checkout',
            self::PaymentSucceeded => 'Paid',
            self::PaymentFailed => 'Payment failed',
            self::CheckoutExpired => 'Checkout expired',
            self::OrderAbandoned => 'Abandoned the order',
            self::CheckoutResumed => 'Came back to finish paying',
        };
    }

    /**
     * The steps every paying buyer passes through, in order, for the funnel.
     *
     * @return list<self>
     */
    public static function funnel(): array
    {
        return [self::TicketPageViewed, self::FormStarted, self::DetailsSubmitted, self::CheckoutOpened, self::PaymentSucceeded];
    }

    /**
     * The steps a browser may report itself; everything else is recorded by the server.
     *
     * @return list<self>
     */
    public static function reportableByBrowser(): array
    {
        return [self::FormStarted, self::CheckoutClosed];
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PaymentSucceeded => 'success',
            self::FormInvalid, self::CheckoutUnavailable, self::PaymentFailed => 'danger',
            self::CheckoutClosed, self::CheckoutExpired, self::OrderAbandoned => 'warning',
            default => 'gray',
        };
    }
}
