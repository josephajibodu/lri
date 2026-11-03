<?php

namespace App\Enums;

/**
 * The steps of a buyer's journey from the ticket page to a paid order, recorded so organisers can see where people stop.
 */
enum CheckoutStep: string
{
    case TicketPageViewed = 'ticket_page_viewed';
    case FormStarted = 'form_started';
    case DetailsSubmitted = 'details_submitted';
    case MethodChosen = 'method_chosen';
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
            self::DetailsSubmitted => 'Submitted their details',
            self::MethodChosen => 'Chose a payment method',
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
}
