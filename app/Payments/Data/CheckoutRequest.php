<?php

namespace App\Payments\Data;

use App\Enums\PaymentMethod;
use App\Support\Money;

/**
 * Everything a provider needs to open a checkout for one payment attempt.
 */
final readonly class CheckoutRequest
{
    /**
     * @param  string  $reference  Our payment attempt's reference, unique for every attempt.
     * @param  list<PaymentMethod>  $methods  The methods to offer; empty offers every method switched on for the account.
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public string $reference,
        public Money $amount,
        public string $customerEmail,
        public ?string $customerName,
        public ?string $customerPhone,
        public string $successUrl,
        public string $cancelUrl,
        public array $methods = [],
        public int $expiresInMinutes = 60,
        public array $metadata = [],
    ) {}
}
