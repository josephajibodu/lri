<?php

namespace App\Payments\Providers;

use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Payments\Contracts\PaymentProvider;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\PaymentUpdate;
use App\Payments\Data\WebhookEvent;
use App\Payments\Exceptions\PaymentProviderException;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A stand-in provider for local work and tests, needing no keys or network. Checkouts are kept in the cache,
 * and simulate() plays the provider's part: it settles a checkout and returns the webhook it would send.
 */
class FakeProvider implements PaymentProvider
{
    public const SignatureHeader = 'X-Fake-Signature';

    /**
     * The checkouts opened through this instance, oldest first, for tests to inspect.
     *
     * @var list<CheckoutRequest>
     */
    private array $requests = [];

    /**
     * @param  array{webhook_secret: string}  $config
     */
    public function __construct(private readonly array $config, private readonly Repository $cache) {}

    public function name(): string
    {
        return 'fake';
    }

    public function supportedMethods(string $currency): array
    {
        return [PaymentMethod::BankTransfer, PaymentMethod::Card, PaymentMethod::Ussd, PaymentMethod::Opay, PaymentMethod::Palmpay];
    }

    public function enabledMethods(string $currency): array
    {
        return $this->supportedMethods($currency);
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $checkoutId = 'fake_chk_'.Str::lower(Str::random(20));
        $expiresAt = CarbonImmutable::now()->addMinutes($request->expiresInMinutes);

        $this->requests[] = $request;
        $this->put($checkoutId, [
            'checkout_id' => $checkoutId,
            'reference' => $request->reference,
            'amount' => $request->amount->amount,
            'currency' => $request->amount->currency,
            'methods' => array_map(fn (PaymentMethod $method): string => $method->value, $request->methods),
            'success_url' => $request->successUrl,
            'cancel_url' => $request->cancelUrl,
            'status' => PaymentStatus::Open->value,
            'payment_id' => null,
            'method' => null,
            'failure_reason' => null,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return new CheckoutSession($checkoutId, url('/payments/fake/'.$checkoutId), $expiresAt);
    }

    public function fetchCheckout(string $checkoutId): PaymentUpdate
    {
        return $this->updateFrom($this->checkout($checkoutId));
    }

    public function verifyWebhook(Request $request): bool
    {
        return hash_equals($this->sign($request->getContent()), (string) $request->header(self::SignatureHeader));
    }

    public function parseWebhook(array $payload): WebhookEvent
    {
        return new WebhookEvent(
            id: $payload['id'] ?? null,
            type: $payload['type'] ?? null,
            createdAt: isset($payload['created_at']) ? CarbonImmutable::parse($payload['created_at']) : null,
            payment: isset($payload['data']['checkout_id']) ? $this->updateFrom($payload['data']) : null,
        );
    }

    /**
     * Settle a checkout the way a buyer and the provider would, and return the webhook payload the provider would send.
     *
     * @return array{id: string, type: string, created_at: string, data: array<string, mixed>}
     */
    public function simulate(
        string $checkoutId,
        PaymentStatus $status,
        ?PaymentMethod $method = PaymentMethod::BankTransfer,
        ?PaymentFailureReason $failureReason = null,
    ): array {
        $checkout = [
            ...$this->checkout($checkoutId),
            'status' => $status->value,
            'payment_id' => $status === PaymentStatus::Expired ? null : 'fake_pay_'.Str::lower(Str::random(20)),
            'method' => $method?->value,
            'failure_reason' => $status === PaymentStatus::Failed ? ($failureReason ?? PaymentFailureReason::Declined)->value : null,
        ];

        $this->put($checkoutId, $checkout);

        return [
            'id' => 'fake_evt_'.Str::lower(Str::random(20)),
            'type' => 'payment.'.$status->value,
            'created_at' => CarbonImmutable::now()->toIso8601String(),
            'data' => $checkout,
        ];
    }

    /**
     * The signature header value for a webhook body.
     */
    public function sign(string $body): string
    {
        return hash_hmac('sha256', $body, $this->config['webhook_secret']);
    }

    /**
     * A stored checkout, for the fake checkout page to show.
     *
     * @return array<string, mixed>
     *
     * @throws PaymentProviderException
     */
    public function checkout(string $checkoutId): array
    {
        return $this->cache->get($this->key($checkoutId))
            ?? throw new PaymentProviderException("No fake checkout [{$checkoutId}].", 'fake', 'NOT_FOUND', 404);
    }

    /**
     * The checkouts opened through this instance, oldest first.
     *
     * @return list<CheckoutRequest>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * @param  array<string, mixed>  $checkout
     */
    private function updateFrom(array $checkout): PaymentUpdate
    {
        $status = PaymentStatus::from($checkout['status']);

        return new PaymentUpdate(
            status: $status,
            providerStatus: $checkout['status'],
            checkoutId: $checkout['checkout_id'],
            paymentId: $checkout['payment_id'] ?? null,
            reference: $checkout['reference'] ?? null,
            method: PaymentMethod::tryFrom((string) ($checkout['method'] ?? '')),
            amount: Money::of($checkout['amount'], $checkout['currency']),
            failureReason: PaymentFailureReason::tryFrom((string) ($checkout['failure_reason'] ?? '')),
            failureMessage: PaymentFailureReason::tryFrom((string) ($checkout['failure_reason'] ?? ''))?->label(),
            occurredAt: CarbonImmutable::now(),
        );
    }

    /**
     * @param  array<string, mixed>  $checkout
     */
    private function put(string $checkoutId, array $checkout): void
    {
        $this->cache->put($this->key($checkoutId), $checkout, now()->addDays(2));
    }

    private function key(string $checkoutId): string
    {
        return 'payments:fake:'.$checkoutId;
    }
}
