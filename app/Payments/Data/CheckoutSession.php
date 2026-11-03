<?php

namespace App\Payments\Data;

use Carbon\CarbonImmutable;

/**
 * A checkout the provider has opened, and where to send the buyer to pay. The overlay names the provider's
 * pop-up checkout script the page can open instead of leaving the site, when it has one.
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $checkoutId,
        public string $checkoutUrl,
        public ?CarbonImmutable $expiresAt,
        public ?string $overlay = null,
    ) {}
}
