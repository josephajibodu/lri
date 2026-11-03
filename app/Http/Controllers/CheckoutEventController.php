<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\RecordCheckoutStep;
use App\Enums\CheckoutStep;
use App\Http\Middleware\IdentifyVisitor;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CheckoutEventController extends Controller
{
    /**
     * Note a step only the browser can see: starting the form, or closing the pop-up checkout. An order is only
     * linked when it belongs to the same visitor, so one browser can't write into another buyer's journey.
     */
    public function __invoke(Request $request, RecordCheckoutStep $recordCheckoutStep): Response
    {
        $validated = $request->validate([
            'step' => ['required', Rule::in(array_map(fn (CheckoutStep $step): string => $step->value, CheckoutStep::reportableByBrowser()))],
            'order' => ['nullable', 'string', 'max:20'],
            'reason' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9_.-]+$/i'],
        ]);

        $visitorId = IdentifyVisitor::visitorId($request);
        $order = filled($validated['order'] ?? null)
            ? Order::query()->where('reference', $validated['order'])->where('visitor_id', $visitorId)->first()
            : null;

        $recordCheckoutStep->handle(CheckoutStep::from($validated['step']), $visitorId, $order, $order?->latestPayment, properties: [
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->noContent();
    }
}
