<?php

namespace App\Payments\Data;

use Carbon\CarbonImmutable;

/**
 * A checkout the provider has opened, and where to send the buyer to pay.
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $checkoutId,
        public string $checkoutUrl,
        public ?CarbonImmutable $expiresAt,
    ) {}
}
