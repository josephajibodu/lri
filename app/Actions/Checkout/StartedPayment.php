<?php

namespace App\Actions\Checkout;

use App\Models\Payment;
use App\Payments\Data\CheckoutSession;

/**
 * A payment attempt and the checkout opened for it.
 */
final readonly class StartedPayment
{
    public function __construct(
        public Payment $payment,
        public CheckoutSession $session,
    ) {}
}
