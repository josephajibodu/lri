<?php

namespace App\Payments\Contracts;

use App\Enums\PaymentMethod;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\PaymentUpdate;
use App\Payments\Data\WebhookEvent;
use App\Payments\Exceptions\PaymentProviderException;
use Illuminate\Http\Request;

/**
 * What every payment provider does for the site, in our own terms. Adding a provider means writing one
 * of these; checkout, webhooks and reporting never see the provider's own codes.
 */
interface PaymentProvider
{
    /**
     * The provider's key, as payment channels and payments store it.
     */
    public function name(): string;

    /**
     * The methods the provider can take in the currency at all, whether or not they are switched on for our account.
     *
     * @return list<PaymentMethod>
     */
    public function supportedMethods(string $currency): array;

    /**
     * The methods switched on for our account in the currency right now.
     *
     * @return list<PaymentMethod>
     *
     * @throws PaymentProviderException
     */
    public function enabledMethods(string $currency): array;

    /**
     * Open a checkout for the buyer to pay on.
     *
     * @throws PaymentProviderException
     */
    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    /**
     * Ask the provider where a checkout stands, for when a webhook is late or never comes.
     *
     * @throws PaymentProviderException
     */
    public function fetchCheckout(string $checkoutId): PaymentUpdate;

    /**
     * Whether an incoming webhook really came from the provider and is recent enough to trust.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Read a webhook's decoded body. Works on stored payloads too, so a webhook can be handled again later.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $payload): WebhookEvent;
}
