<?php

namespace App\Http\Controllers;

use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Payments\Exceptions\PaymentProviderException;
use App\Payments\PaymentManager;
use App\Payments\Providers\FakeProvider;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A pretend payment page for the fake provider, so the whole checkout can be tried locally. Never routed in production.
 */
class FakeCheckoutController extends Controller
{
    public function __construct(private PaymentManager $payments) {}

    /**
     * Show the fake checkout with buttons to pay, fail or leave.
     */
    public function show(string $checkoutId): View
    {
        $checkout = $this->checkout($checkoutId);

        return view('payments.fake-checkout', [
            'checkout' => $checkout,
            'amount' => Money::of($checkout['amount'], $checkout['currency']),
            'method' => PaymentMethod::tryFrom($checkout['methods'][0] ?? '') ?? PaymentMethod::BankTransfer,
            'failureReasons' => [PaymentFailureReason::InsufficientFunds, PaymentFailureReason::Declined, PaymentFailureReason::AuthenticationFailed],
        ]);
    }

    /**
     * Settle the fake checkout the way the buyer chose, then send them back to the site as the provider would.
     */
    public function settle(Request $request, string $checkoutId): RedirectResponse
    {
        $checkout = $this->checkout($checkoutId);
        $validated = $request->validate([
            'outcome' => ['required', Rule::in(['paid', 'failed', 'left'])],
            'reason' => ['nullable', Rule::enum(PaymentFailureReason::class)],
        ]);

        if ($validated['outcome'] === 'left') {
            return redirect()->away($this->withCheckoutId($checkout['cancel_url'], $checkoutId));
        }

        $this->provider()->simulate(
            $checkoutId,
            $validated['outcome'] === 'paid' ? PaymentStatus::Succeeded : PaymentStatus::Failed,
            PaymentMethod::tryFrom($checkout['methods'][0] ?? '') ?? PaymentMethod::BankTransfer,
            PaymentFailureReason::tryFrom($validated['reason'] ?? ''),
        );

        return redirect()->away($this->withCheckoutId($checkout['success_url'], $checkoutId));
    }

    /**
     * @return array<string, mixed>
     */
    private function checkout(string $checkoutId): array
    {
        try {
            return $this->provider()->checkout($checkoutId);
        } catch (PaymentProviderException) {
            abort(404);
        }
    }

    private function provider(): FakeProvider
    {
        return $this->payments->driver('fake');
    }

    /**
     * Add the checkout id to a return link, the way Bachs does.
     */
    private function withCheckoutId(string $url, string $checkoutId): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'checkout_id='.$checkoutId;
    }
}
