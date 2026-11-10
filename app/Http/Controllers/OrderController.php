<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\PlaceOrder;
use App\Actions\Checkout\RecordCheckoutStep;
use App\Actions\Checkout\RefreshPayment;
use App\Actions\Checkout\StartPayment;
use App\Enums\CheckoutStep;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\CheckoutUnavailable;
use App\Exceptions\CouponUnavailable;
use App\Exceptions\TicketsUnavailable;
use App\Http\Middleware\IdentifyVisitor;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\PaymentChannel;
use App\Models\TicketType;
use App\Payments\Exceptions\PaymentProviderException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private RecordCheckoutStep $recordCheckoutStep) {}

    /**
     * How long the order page waits before asking the provider again about an open payment.
     */
    private const RecheckAfterSeconds = 5;

    /**
     * Where the reminder email's button leads: back to the ticket form, filled in with what the buyer typed last time,
     * so they can pick up where they left off. The visit is tagged as coming from the reminder, so a sale it brings
     * in shows under where buyers come from. A buyer who has paid since is sent to their receipt instead.
     */
    public function pickUp(Request $request, Order $order): RedirectResponse
    {
        if ($order->status === OrderStatus::Paid) {
            return redirect($order->receiptUrl());
        }

        $this->recordCheckoutStep->handle(CheckoutStep::ReminderOpened, IdentifyVisitor::visitorId($request), order: $order);

        return redirect()
            ->to(route('disco-run', ['utm_source' => 'email', 'utm_medium' => 'reminder', 'utm_campaign' => 'abandoned-order']).'#tickets')
            ->withInput(self::checkoutFormFor($order));
    }

    /**
     * What an order's buyer typed into the ticket form, ready to fill it in again.
     *
     * @return array{quantity: int, name: string, email: string, phone: ?string, coupon: ?string, marketing_consent: bool}
     */
    public static function checkoutFormFor(Order $order): array
    {
        return [
            'quantity' => $order->quantity,
            'name' => $order->buyer_name,
            'email' => $order->buyer_email,
            'phone' => $order->buyer_phone,
            'coupon' => $order->coupon?->code,
            'marketing_consent' => $order->marketing_consent,
        ];
    }

    /**
     * Take the buyer's details, hold their tickets and open a checkout with the way to pay they chose.
     */
    public function store(StoreOrderRequest $request, PlaceOrder $placeOrder, StartPayment $startPayment): RedirectResponse|JsonResponse
    {
        $ticketType = TicketType::query()->where('slug', $request->validated('ticket_type'))->firstOrFail();

        try {
            $order = $placeOrder->handle($ticketType, (int) $request->validated('quantity'), [
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'phone' => $request->validated('phone'),
                'marketing_consent' => $request->validated('marketing_consent'),
            ], IdentifyVisitor::visitorId($request), IdentifyVisitor::attributionFor($request), $request->validated('coupon'), $request->ip());
        } catch (TicketsUnavailable $exception) {
            $this->recordCheckoutStep->handle(CheckoutStep::FormInvalid, IdentifyVisitor::visitorId($request), properties: [
                'fields' => ['quantity'],
                'reason' => 'tickets_unavailable',
            ]);

            throw ValidationException::withMessages(['quantity' => $exception->getMessage()])
                ->redirectTo(strtok(url()->previous(), '#').'#tickets');
        } catch (CouponUnavailable $exception) {
            $this->recordCheckoutStep->handle(CheckoutStep::FormInvalid, IdentifyVisitor::visitorId($request), properties: [
                'fields' => ['coupon'],
                'reason' => 'coupon_unavailable',
            ]);

            throw ValidationException::withMessages(['coupon' => $exception->getMessage()])
                ->redirectTo(strtok(url()->previous(), '#').'#tickets');
        }

        $this->recordCheckoutStep->handle(CheckoutStep::DetailsSubmitted, order: $order, properties: [
            'quantity' => $order->quantity,
            'marketing_consent' => $order->marketing_consent,
            'phone_given' => $order->buyer_phone !== null,
            'coupon' => $order->coupon?->code,
        ]);

        return $this->openCheckout($request, $order, PaymentChannel::query()->findOrFail($request->validated('channel')), $startPayment);
    }

    /**
     * The buyer's order while it isn't paid: confirming, or not paid yet with ways to try again. This is also where
     * the checkout sends the buyer back to; coming back, the payment is checked with the provider straight away,
     * and a paid order moves on to its receipt.
     */
    public function show(Request $request, Order $order, RefreshPayment $refreshPayment): View|RedirectResponse
    {
        $order->load('latestPayment');
        $from = $request->query('from');

        if (in_array($from, ['checkout', 'cancelled'], true) && $order->latestPayment && ! $order->checkoutEvents()
            ->where('step', CheckoutStep::ReturnedFromCheckout)->where('payment_id', $order->latestPayment->id)->exists()) {
            $this->recordCheckoutStep->handle(CheckoutStep::ReturnedFromCheckout, IdentifyVisitor::visitorId($request), $order, $order->latestPayment, properties: [
                'from' => $from,
            ]);
        }

        if ($from === 'checkout') {
            $this->refreshIfDue($order, $refreshPayment);
        }

        if ($order->status === OrderStatus::Paid) {
            return redirect()->to($order->receiptUrl());
        }

        $order->load(['ticketType', 'coupon']);

        return view('orders.show', [
            'order' => $order,
            'state' => $this->state($order),
            'from' => $from,
            'channels' => PaymentChannel::query()->offeredIn($order->currency)->get(),
            'pollUrl' => $order->signedUrl('orders.status'),
            'payUrl' => $order->signedUrl('orders.pay'),
            'discoRun' => config('lri.disco_run'),
        ]);
    }

    /**
     * A paid order's receipt, at an address of its own the buyer can come back to: their tickets, what they paid
     * and how. An order that isn't paid sends them to its order page instead.
     */
    public function receipt(Order $order): View|RedirectResponse
    {
        if ($order->status !== OrderStatus::Paid) {
            return redirect()->to($order->statusUrl());
        }

        $order->load(['ticketType', 'tickets', 'latestPayment', 'coupon']);

        return view('orders.receipt', [
            'order' => $order,
            'payment' => $order->latestPayment,
            'event' => $order->ticketType->eventDetails(),
            'when' => $order->ticketType->eventWhen(),
            'discoRun' => config('lri.disco_run'),
        ]);
    }

    /**
     * Where the order stands, for the order page to check while it waits. An open payment is checked with the
     * provider every few seconds, so the buyer isn't kept waiting on a webhook.
     */
    public function status(Order $order, RefreshPayment $refreshPayment): JsonResponse
    {
        $this->refreshIfDue($order, $refreshPayment);

        return response()->json(['state' => $this->state($order)]);
    }

    /**
     * Try paying for an order again, with the same or another way to pay.
     */
    public function pay(Request $request, Order $order, StartPayment $startPayment): RedirectResponse|JsonResponse
    {
        $validated = $request->validate(
            ['channel' => ['required', 'integer', Rule::exists('payment_channels', 'id')->where('is_enabled', true)]],
            ['channel.required' => 'Choose how you’d like to pay.', 'channel.exists' => 'That way to pay isn’t available right now. Choose another one.'],
        );

        $this->recordCheckoutStep->handle(CheckoutStep::CheckoutResumed, IdentifyVisitor::visitorId($request), $order, properties: [
            'attempt' => $order->payments()->count() + 1,
            'state' => $this->state($order),
        ]);

        return $this->openCheckout($request, $order, PaymentChannel::query()->findOrFail($validated['channel']), $startPayment);
    }

    /**
     * Open a checkout and send the buyer to it: as JSON for the page's pop-up checkout, or as a redirect without JavaScript.
     * When it can't be opened, the buyer lands on their order page to try again.
     */
    private function openCheckout(Request $request, Order $order, PaymentChannel $channel, StartPayment $startPayment): RedirectResponse|JsonResponse
    {
        $visitorId = IdentifyVisitor::visitorId($request);
        $this->recordCheckoutStep->handle(CheckoutStep::MethodChosen, $visitorId, $order, method: $channel->method, properties: [
            'featured' => $channel->is_featured,
        ]);

        try {
            $started = $startPayment->handle($order, $channel);
        } catch (CheckoutUnavailable|TicketsUnavailable $exception) {
            $this->recordCheckoutStep->handle(CheckoutStep::CheckoutUnavailable, $visitorId, $order, $order->latestPayment()->first(), $channel->method, [
                'reason' => $exception instanceof TicketsUnavailable ? 'tickets_unavailable' : $exception->reason,
            ]);

            $request->session()->flash('checkout_error', $exception->getMessage());

            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage(), 'status_url' => $order->statusUrl()], 409);
            }

            return redirect()->to($order->statusUrl());
        }

        $this->recordCheckoutStep->handle(CheckoutStep::CheckoutOpened, $visitorId, $order, $started->payment, properties: [
            'provider' => $started->payment->provider,
            'overlay' => $request->expectsJson() && $started->session->overlay !== null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'order' => $order->reference,
                'checkout_url' => $started->session->checkoutUrl,
                'overlay' => $started->session->overlay,
                'status_url' => $order->statusUrl(['from' => 'checkout']),
                'cancelled_url' => $order->statusUrl(['from' => 'cancelled']),
            ]);
        }

        return redirect()->away($started->session->checkoutUrl);
    }

    /**
     * Ask the provider about the order's open payment, unless it was asked in the last few seconds. A provider
     * that doesn't answer is reported and the order is left as it was. Asking counts whether or not it answers, so
     * refreshing the page can't hammer a provider that's struggling.
     */
    private function refreshIfDue(Order $order, RefreshPayment $refreshPayment): void
    {
        $payment = $order->latestPayment;

        if ($order->status !== OrderStatus::Pending
            || $payment?->status !== PaymentStatus::Open
            || $payment->last_checked_at?->gte(now()->subSeconds(self::RecheckAfterSeconds))) {
            return;
        }

        $payment->update(['last_checked_at' => now()]);

        try {
            $refreshPayment->handle($payment);
            $order->refresh()->load('latestPayment');
        } catch (PaymentProviderException $exception) {
            report($exception);
        }
    }

    /**
     * The order as the buyer's page shows it. A try that failed on a checkout that's still open reads as failed, so the
     * buyer is told what happened and offered another way to pay.
     *
     * @return 'paid'|'refunded'|'disputed'|'test'|'abandoned'|'waiting'|'failed'|'underpaid'|'unpaid'
     */
    private function state(Order $order): string
    {
        return match ($order->status) {
            OrderStatus::Paid => 'paid',
            OrderStatus::Refunded => 'refunded',
            OrderStatus::Disputed => 'disputed',
            OrderStatus::TestPayment => 'test',
            OrderStatus::Abandoned => 'abandoned',
            OrderStatus::Pending => match ($order->latestPayment?->status) {
                PaymentStatus::Open => $order->latestPayment->failure_reason === null ? 'waiting' : 'failed',
                PaymentStatus::Failed => 'failed',
                PaymentStatus::Underpaid => 'underpaid',
                default => 'unpaid',
            },
        };
    }
}
