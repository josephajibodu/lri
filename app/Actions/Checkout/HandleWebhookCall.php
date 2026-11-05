<?php

namespace App\Actions\Checkout;

use App\Models\Payment;
use App\Models\WebhookCall;
use App\Payments\Data\PaymentUpdate;
use App\Payments\PaymentManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Store a provider's webhook, then apply it to the payment it is about. A stored call can be handled again
 * later (payments:replay-webhook), and one already handled is recognised when the provider sends it twice.
 */
class HandleWebhookCall
{
    public function __construct(
        private PaymentManager $payments,
        private RecordPaymentUpdate $recordPaymentUpdate,
    ) {}

    /**
     * Keep a verified webhook, or return the one already kept with the same event id.
     *
     * @param  array<string, mixed>  $payload
     */
    public function store(string $provider, array $payload): WebhookCall
    {
        $event = $this->payments->driver($provider)->parseWebhook($payload);
        $attributes = [
            'provider' => $provider,
            'event_id' => $event->id,
            'type' => $event->type,
            'payload' => $payload,
            'signature_valid' => true,
            'event_created_at' => $event->createdAt,
        ];

        if ($event->id === null) {
            return WebhookCall::query()->create($attributes);
        }

        try {
            return WebhookCall::query()->firstOrCreate(['provider' => $provider, 'event_id' => $event->id], $attributes);
        } catch (UniqueConstraintViolationException) {
            return WebhookCall::query()->where('provider', $provider)->where('event_id', $event->id)->firstOrFail();
        }
    }

    /**
     * Apply a stored webhook to its payment. Events the site doesn't act on, and events for payments it doesn't know,
     * are marked handled so the provider stops sending them; a failure is noted on the call and thrown so the
     * provider tries again.
     *
     * @throws Throwable
     */
    public function handle(WebhookCall $call): WebhookCall
    {
        try {
            $update = $this->payments->driver($call->provider)->parseWebhook($call->payload)->payment;
            $payment = $update ? $this->paymentFor($call->provider, $update) : null;

            if ($payment) {
                $this->recordPaymentUpdate->handle($payment, $update);
            }

            $call->update([
                'processed_at' => now(),
                'failed_at' => null,
                'error' => $update && ! $payment ? 'No payment on the site matches this event.' : null,
            ]);
        } catch (Throwable $exception) {
            $call->update(['failed_at' => now(), 'error' => Str::limit($exception->getMessage(), 1000)]);

            throw $exception;
        }

        return $call;
    }

    /**
     * The payment attempt an update is about: by the provider's checkout id, by our reference or, for refunds and
     * disputes, which only name the charge, by the provider's id for it.
     */
    private function paymentFor(string $provider, PaymentUpdate $update): ?Payment
    {
        if ($update->checkoutId !== null) {
            $payment = Payment::query()->where('provider', $provider)->where('provider_checkout_id', $update->checkoutId)->first();

            if ($payment) {
                return $payment;
            }
        }

        if ($update->reference !== null) {
            $payment = Payment::query()->where('provider', $provider)->where('reference', $update->reference)->first();

            if ($payment) {
                return $payment;
            }
        }

        return $update->paymentId !== null
            ? Payment::query()->where('provider', $provider)->where('provider_payment_id', $update->paymentId)->first()
            : null;
    }
}
