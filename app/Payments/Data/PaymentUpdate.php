<?php

namespace App\Payments\Data;

use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Where a payment attempt stands according to the provider, from a webhook or from asking it directly.
 * A failure reason can come with an open status: the last try failed, but the buyer can still pay.
 */
final readonly class PaymentUpdate
{
    /**
     * @param  string  $providerStatus  The provider's own status, kept for debugging.
     * @param  ?Money  $amount  The money actually received, not what the checkout asked for. Without it, a success isn't believed.
     * @param  ?string  $refundedAmount  How much went back, as a decimal in the payment's own currency. Refund messages don't name a currency.
     * @param  bool  $endsDispute  The bank ruled for us, which is the only thing that lets a disputed payment count as paid again.
     */
    public function __construct(
        public PaymentStatus $status,
        public string $providerStatus,
        public ?string $checkoutId = null,
        public ?string $paymentId = null,
        public ?string $reference = null,
        public ?PaymentMethod $method = null,
        public ?Money $amount = null,
        public ?Money $fee = null,
        public ?PaymentFailureReason $failureReason = null,
        public ?string $failureMessage = null,
        public ?CarbonImmutable $occurredAt = null,
        public ?string $refundedAmount = null,
        public bool $endsDispute = false,
    ) {}
}
