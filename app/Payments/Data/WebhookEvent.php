<?php

namespace App\Payments\Data;

use Carbon\CarbonImmutable;

/**
 * A webhook read into our terms. The payment update is null for events the site does not act on.
 */
final readonly class WebhookEvent
{
    public function __construct(
        public ?string $id,
        public ?string $type,
        public ?CarbonImmutable $createdAt,
        public ?PaymentUpdate $payment,
    ) {}
}
