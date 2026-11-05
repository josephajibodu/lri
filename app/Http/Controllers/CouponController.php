<?php

namespace App\Http\Controllers;

use App\Exceptions\CouponUnavailable;
use App\Models\Coupon;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    /**
     * Check a coupon for the ticket form, and say what it takes off at each quantity, so the form's total stays right
     * as the buyer changes the number of tickets. Placing the order checks the coupon again.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(
            ['ticket_type' => ['required', 'string', Rule::exists('ticket_types', 'slug')], 'coupon' => ['required', 'string', 'max:40']],
            ['coupon.required' => 'Type your coupon code first.', 'coupon.max' => CouponUnavailable::notFound()->getMessage()],
        );

        $ticketType = TicketType::query()->where('slug', $validated['ticket_type'])->firstOrFail();
        $coupon = Coupon::query()->withCode($validated['coupon'])->first();
        $problem = $coupon === null ? CouponUnavailable::notFound() : $coupon->whyUnusableOn($ticketType);

        if ($problem !== null) {
            throw ValidationException::withMessages(['coupon' => $problem->getMessage()]);
        }

        return response()->json([
            'code' => $coupon->code,
            'description' => $coupon->describe(),
            'discounts' => collect(range(1, $ticketType->max_per_order))
                ->mapWithKeys(fn (int $quantity): array => [$quantity => $coupon->discountFor($ticketType->unitPrice(), $quantity)->amount]),
        ]);
    }
}
