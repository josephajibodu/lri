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
            ], IdentifyVisitor::visitorId($request), IdentifyVisitor::attributionFor($request));
        } catch (TicketsUnavailable $exception) {
            $this->recordCheckoutStep->handle(CheckoutStep::FormInvalid, IdentifyVisitor::visitorId($request), properties: [
                'fields' => ['quantity'],
                'reason' => 'tickets_unavailable',
            ]);

            throw ValidationException::withMessages(['quantity' => $exception->getMessage()])
                ->redirectTo(strtok(url()->previous(), '#').'#tickets');
        }

        $this->recordCheckoutStep->handle(CheckoutStep::DetailsSubmitted, order: $order, properties: [
            'quantity' => $order->quantity,
            'marketing_consent' => $order->marketing_consent,
            'phone_given' => $order->buyer_phone !== null,
        ]);

        return $this->openCheckout($request, $order, PaymentChannel::query()->findOrFail($request->validated('channel')), $startPayment);
    }

    /**
     * The buyer's order: confirming, paid with their tickets, or not paid yet with ways to try again.
     */
    public function show(Request $request, Order $order): View
    {
        $order->load(['ticketType', 'tickets', 'latestPayment']);
        $from = $request->query('from');

        if (in_array($from, ['checkout', 'cancelled'], true) && $order->latestPayment && ! $order->checkoutEvents()
            ->where('step', CheckoutStep::ReturnedFromCheckout)->where('payment_id', $order->latestPayment->id)->exists()) {
            $this->recordCheckoutStep->handle(CheckoutStep::ReturnedFromCheckout, IdentifyVisitor::visitorId($request), $order, $order->latestPayment, properties: [
                'from' => $from,
            ]);
        }

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
     * Where the order stands, for the order page to check while it waits. An open payment is checked with the
     * provider every few seconds, so the buyer isn't kept waiting on a webhook.
     */
    public function status(Order $order, RefreshPayment $refreshPayment): JsonResponse
    {
        $payment = $order->latestPayment;

        if ($order->status === OrderStatus::Pending
            && $payment?->status === PaymentStatus::Open
            && ($payment->last_checked_at === null || $payment->last_checked_at->lt(now()->subSeconds(self::RecheckAfterSeconds)))) {
            try {
                $refreshPayment->handle($payment);
                $order->refresh()->load('latestPayment');
            } catch (PaymentProviderException $exception) {
                report($exception);
            }
        }

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
                'reason' => $exception instanceof TicketsUnavailable ? 'tickets_unavailable' : 'provider_error',
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage(), 'status_url' => $order->statusUrl()], 409);
            }

            return redirect()->to($order->statusUrl())->with('checkout_error', $exception->getMessage());
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
     * The order as the buyer's page shows it.
     *
     * @return 'paid'|'refunded'|'abandoned'|'waiting'|'failed'|'underpaid'|'unpaid'
     */
    private function state(Order $order): string
    {
        return match ($order->status) {
            OrderStatus::Paid => 'paid',
            OrderStatus::Refunded => 'refunded',
            OrderStatus::Abandoned => 'abandoned',
            OrderStatus::Pending => match ($order->latestPayment?->status) {
                PaymentStatus::Open => 'waiting',
                PaymentStatus::Failed => 'failed',
                PaymentStatus::Underpaid => 'underpaid',
                default => 'unpaid',
            },
        };
    }
}
