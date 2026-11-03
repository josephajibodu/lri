<?php

namespace App\Actions\Checkout;

use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderException;
use App\Payments\PaymentManager;

/**
 * Ask the provider directly where a payment attempt stands and record it, for when a webhook is late or lost.
 */
class RefreshPayment
{
    public function __construct(
        private PaymentManager $payments,
        private RecordPaymentUpdate $recordPaymentUpdate,
    ) {}

    /**
     * @throws PaymentProviderException
     */
    public function handle(Payment $payment): Payment
    {
        if ($payment->provider_checkout_id === null) {
            return $payment;
        }

        $update = $this->payments->driver($payment->provider)->fetchCheckout($payment->provider_checkout_id);

        return $this->recordPaymentUpdate->handle($payment, $update);
    }
}
