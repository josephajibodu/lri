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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Bachs (docs.bachs.io): hosted checkout sessions, paid in naira by card or bank transfer, among others.
 */
class BachsProvider implements PaymentProvider
{
    private const SandboxUrl = 'https://sandbox-api.bachs.io';

    private const LiveUrl = 'https://api.bachs.io';

    /**
     * Bachs calls each method-and-currency pair a corridor. These are the ones the site can offer.
     *
     * @var array<string, array{0: PaymentMethod, 1: string}>
     */
    private const Corridors = [
        'NGN_CARD' => [PaymentMethod::Card, 'NGN'],
        'NGN_BANK_TRANSFER' => [PaymentMethod::BankTransfer, 'NGN'],
        'USD_CARD' => [PaymentMethod::Card, 'USD'],
        'MOMO_GHS' => [PaymentMethod::MobileMoney, 'GHS'],
        'MOMO_KES' => [PaymentMethod::MobileMoney, 'KES'],
        'MOMO_TZS' => [PaymentMethod::MobileMoney, 'TZS'],
        'MOMO_UGX' => [PaymentMethod::MobileMoney, 'UGX'],
        'MOMO_XAF' => [PaymentMethod::MobileMoney, 'XAF'],
        'MOMO_XOF' => [PaymentMethod::MobileMoney, 'XOF'],
        'MOMO_RWF' => [PaymentMethod::MobileMoney, 'RWF'],
        'MOMO_MWK' => [PaymentMethod::MobileMoney, 'MWK'],
        'MOMO_ZMW' => [PaymentMethod::MobileMoney, 'ZMW'],
    ];

    /**
     * @param  array{secret_key: ?string, webhook_secret: ?string, base_url: ?string, webhook_tolerance: int, connect_timeout: int, timeout: int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'bachs';
    }

    public function supportedMethods(string $currency): array
    {
        return array_map(
            fn (string $method): PaymentMethod => PaymentMethod::from($method),
            array_keys($this->corridorsIn($currency)),
        );
    }

    public function enabledMethods(string $currency): array
    {
        $settings = $this->send(fn (PendingRequest $http) => $http->get('/v1/accounts/checkout/settings'))->json();

        return collect($this->corridorsIn($currency))
            ->filter(fn (string $corridor): bool => (bool) data_get($settings, "enabled_payment_methods.{$corridor}.enabled"))
            ->keys()
            ->map(fn (string $method): PaymentMethod => PaymentMethod::from($method))
            ->values()
            ->all();
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $corridors = $this->corridorsIn($request->amount->currency);

        $paymentMethodTypes = array_map(function (PaymentMethod $method) use ($corridors, $request): string {
            return $corridors[$method->value]
                ?? throw new InvalidArgumentException("Bachs cannot take {$method->label()} in {$request->amount->currency}.");
        }, $request->methods);

        $body = array_filter([
            'pricing' => [
                'currency' => $request->amount->currency,
                'amount' => $request->amount->toDecimal(),
            ],
            'customer' => array_filter([
                'email' => $request->customerEmail,
                'name' => filled($request->customerName) ? $request->customerName : null,
                'phone_number' => preg_match('/^\+\d{8,15}$/', (string) $request->customerPhone) ? $request->customerPhone : null,
            ]),
            'success_url' => $request->successUrl,
            'cancel_url' => $request->cancelUrl,
            'payment_method_types' => $paymentMethodTypes ?: null,
            'reference' => $request->reference,
            'metadata' => $request->metadata ?: null,
            'expires_in_minutes' => $request->expiresInMinutes,
        ], fn (mixed $value): bool => $value !== null);

        $session = $this->send(fn (PendingRequest $http) => $http
            ->withHeaders(['Idempotency-Key' => $request->reference])
            ->post('/v1/checkout-sessions', $body))->json();

        return new CheckoutSession(
            checkoutId: $session['checkout_id'],
            checkoutUrl: $session['checkout_url'],
            expiresAt: $this->date($session['expires_at'] ?? null),
        );
    }

    public function fetchCheckout(string $checkoutId): PaymentUpdate
    {
        $session = $this->send(fn (PendingRequest $http) => $http->get('/v1/checkout-sessions/'.rawurlencode($checkoutId)))->json();
        $charge = $session['charge'] ?? null;
        $sessionStatus = strtolower($session['status']);
        $chargeStatus = $charge ? strtolower($charge['status']) : null;

        $status = match (true) {
            $sessionStatus === 'open' => PaymentStatus::Open,
            $chargeStatus !== null => $this->statusFromCharge($chargeStatus),
            $sessionStatus === 'completed' => PaymentStatus::Succeeded,
            $sessionStatus === 'expired' => PaymentStatus::Expired,
            default => PaymentStatus::Abandoned,
        };

        $failureMessage = $chargeStatus === 'failed'
            ? ($charge['message'] ?? collect($charge['status_history'] ?? [])->last()['reason'] ?? null)
            : null;

        return new PaymentUpdate(
            status: $status,
            providerStatus: $chargeStatus ? "{$sessionStatus}/{$chargeStatus}" : $sessionStatus,
            checkoutId: $session['checkout_id'],
            paymentId: $charge['payment_id'] ?? null,
            reference: $session['reference'] ?? null,
            method: $this->methodFrom($session['payment_method'] ?? $charge['payment_method'] ?? null),
            amount: $this->money($session['amount'] ?? null, $session['currency'] ?? null),
            fee: $this->money($charge['fees']['amount'] ?? null, $charge['fees']['currency'] ?? null),
            failureReason: $chargeStatus === 'failed' ? $this->failureReasonFrom($failureMessage) : null,
            failureMessage: $failureMessage,
            occurredAt: $this->date($session['updated_at'] ?? null),
        );
    }

    /**
     * Bachs signs "{timestamp}.{raw body}" with HMAC-SHA256. The X-Bachs-Signature-V2 header can carry
     * several signatures while a secret is being rotated, and any one of them matching is enough.
     */
    public function verifyWebhook(Request $request): bool
    {
        $secret = $this->config['webhook_secret'] ?? null;

        if (blank($secret)) {
            return false;
        }

        [$timestamp, $signatures] = $this->signatureParts($request);

        if (! ctype_digit((string) $timestamp) || abs(now()->getTimestamp() - (int) $timestamp) > $this->config['webhook_tolerance']) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhook(array $payload): WebhookEvent
    {
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];

        return new WebhookEvent(
            id: $payload['id'] ?? null,
            type: $type,
            createdAt: $this->date($payload['created_at'] ?? null),
            payment: match ($type) {
                'collection.succeeded', 'collection.failed', 'collection.underpaid' => $this->updateFromCollection($data, $payload),
                'checkout.completed' => ($data['payment_status'] ?? null) === 'paid' ? $this->updateFromCompletedCheckout($data, $payload) : null,
                'checkout.expired' => new PaymentUpdate(
                    status: PaymentStatus::Expired,
                    providerStatus: 'expired',
                    checkoutId: $data['checkout_id'] ?? null,
                    reference: $data['reference'] ?? null,
                    occurredAt: $this->date($payload['created_at'] ?? null),
                ),
                default => null,
            },
        );
    }

    /**
     * Read a collection.* event: a charge on a checkout succeeded, failed or came up short.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $payload
     */
    private function updateFromCollection(array $data, array $payload): PaymentUpdate
    {
        $providerStatus = strtolower($data['status'] ?? '');

        $status = match ($payload['type']) {
            'collection.underpaid' => PaymentStatus::Underpaid,
            'collection.failed' => $providerStatus === 'expired' ? PaymentStatus::Expired : PaymentStatus::Failed,
            default => PaymentStatus::Succeeded,
        };

        return new PaymentUpdate(
            status: $status,
            providerStatus: $providerStatus,
            checkoutId: $data['checkout_id'] ?? null,
            paymentId: $data['charge_id'] ?? null,
            reference: $data['reference'] ?? null,
            method: $this->methodFrom($data['payment_method_details']['type'] ?? null),
            amount: $this->money($data['amount'] ?? null, $data['currency'] ?? null),
            fee: $this->money($data['processing_fee'] ?? null, $data['processing_fee_currency'] ?? null),
            failureReason: $status === PaymentStatus::Failed ? $this->failureReasonFrom($data['reason'] ?? null) : null,
            failureMessage: $status === PaymentStatus::Failed ? ($data['reason'] ?? null) : null,
            occurredAt: $this->date($payload['created_at'] ?? null),
        );
    }

    /**
     * Read a checkout.completed event for a paid checkout. It can arrive before or after collection.succeeded.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $payload
     */
    private function updateFromCompletedCheckout(array $data, array $payload): PaymentUpdate
    {
        return new PaymentUpdate(
            status: PaymentStatus::Succeeded,
            providerStatus: 'completed',
            checkoutId: $data['checkout_id'] ?? null,
            paymentId: $data['charge']['id'] ?? null,
            reference: $data['reference'] ?? null,
            amount: $this->money($data['amount'] ?? null, $data['currency'] ?? null),
            occurredAt: $this->date($payload['created_at'] ?? null),
        );
    }

    /**
     * Our status for a Bachs charge status.
     */
    private function statusFromCharge(string $status): PaymentStatus
    {
        return match ($status) {
            'succeeded', 'accepted', 'overpaid' => PaymentStatus::Succeeded,
            'underpaid' => PaymentStatus::Underpaid,
            'failed' => PaymentStatus::Failed,
            'expired' => PaymentStatus::Expired,
            'cancelled', 'canceled' => PaymentStatus::Abandoned,
            'refunded', 'auto_refunded' => PaymentStatus::Refunded,
            'partially_refunded' => PaymentStatus::PartiallyRefunded,
            default => PaymentStatus::Open,
        };
    }

    /**
     * Our method for a Bachs payment method code, such as NGN_BANK_TRANSFER, CARD or bank_transfer.
     */
    private function methodFrom(?string $code): ?PaymentMethod
    {
        $code = strtoupper((string) $code);

        return match (true) {
            str_ends_with($code, 'CARD') => PaymentMethod::Card,
            str_ends_with($code, 'BANK_TRANSFER') => PaymentMethod::BankTransfer,
            str_starts_with($code, 'MOMO_'), $code === 'MOBILE_MONEY' => PaymentMethod::MobileMoney,
            $code === 'CRYPTO' => PaymentMethod::Crypto,
            default => null,
        };
    }

    /**
     * Our failure reason for Bachs's free-text one. Bachs does not promise fixed wording,
     * so this matches common phrases and falls back to unknown; the text itself is kept too.
     */
    private function failureReasonFrom(?string $reason): PaymentFailureReason
    {
        $reason = strtolower((string) $reason);

        return match (true) {
            str_contains($reason, 'insufficient') => PaymentFailureReason::InsufficientFunds,
            (bool) preg_match('/expired.card|card.expired/', $reason) => PaymentFailureReason::ExpiredCard,
            (bool) preg_match('/\b(otp|pin|3ds|3-d secure)\b|authenticat/', $reason) => PaymentFailureReason::AuthenticationFailed,
            (bool) preg_match('/declin|authori[sz]ation failed|not authori[sz]ed/', $reason) => PaymentFailureReason::Declined,
            str_contains($reason, 'invalid') => PaymentFailureReason::InvalidDetails,
            (bool) preg_match('/timed.out|timeout/', $reason) => PaymentFailureReason::TimedOut,
            (bool) preg_match('/provider|processing error/', $reason) => PaymentFailureReason::ProviderError,
            default => PaymentFailureReason::Unknown,
        };
    }

    /**
     * The corridors Bachs offers in a currency, keyed by our method.
     *
     * @return array<string, string>
     */
    private function corridorsIn(string $currency): array
    {
        return collect(self::Corridors)
            ->filter(fn (array $corridor): bool => $corridor[1] === strtoupper($currency))
            ->mapWithKeys(fn (array $corridor, string $code): array => [$corridor[0]->value => $code])
            ->all();
    }

    /**
     * The timestamp and signatures from a webhook, preferring the V2 header.
     *
     * @return array{0: ?string, 1: list<string>}
     */
    private function signatureParts(Request $request): array
    {
        $v2 = (string) $request->header('X-Bachs-Signature-V2');

        if ($v2 !== '') {
            $timestamp = null;
            $signatures = [];

            foreach (explode(',', $v2) as $part) {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

                if ($key === 't') {
                    $timestamp = $value;
                } elseif ($key === 'v1' && $value !== '') {
                    $signatures[] = $value;
                }
            }

            return [$timestamp, $signatures];
        }

        return [$request->header('X-Bachs-Timestamp'), array_filter([(string) $request->header('X-Bachs-Signature')])];
    }

    /**
     * Send a request to Bachs, retrying brief outages and rate limits, and turn any failure into a PaymentProviderException.
     * Retrying a checkout is safe because it carries an idempotency key.
     *
     * @param  callable(PendingRequest): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            $response = $request($this->http());
        } catch (ConnectionException $exception) {
            throw new PaymentProviderException('Could not reach Bachs: '.$exception->getMessage(), 'bachs', previous: $exception);
        }

        if ($response->failed()) {
            throw PaymentProviderException::fromResponse('bachs', $response);
        }

        return $response;
    }

    private function http(): PendingRequest
    {
        $secretKey = $this->config['secret_key'] ?? null;

        if (blank($secretKey)) {
            throw new PaymentProviderException('Bachs is not set up: BACHS_SECRET_KEY is empty.', 'bachs');
        }

        return Http::baseUrl($this->config['base_url'] ?: (str_starts_with($secretKey, 'sk_live_') ? self::LiveUrl : self::SandboxUrl))
            ->withToken($secretKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout($this->config['connect_timeout'])
            ->timeout($this->config['timeout'])
            ->retry([200, 1000], 0, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 429)), throw: false);
    }

    private function money(?string $amount, ?string $currency): ?Money
    {
        return $amount !== null && filled($currency) ? Money::fromDecimal($amount, $currency) : null;
    }

    private function date(?string $value): ?CarbonImmutable
    {
        return $value ? CarbonImmutable::parse($value) : null;
    }
}
