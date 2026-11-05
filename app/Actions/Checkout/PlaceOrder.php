<?php

namespace App\Actions\Checkout;

use App\Events\OrderPlaced;
use App\Exceptions\CouponUnavailable;
use App\Exceptions\TicketsUnavailable;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

/**
 * Create a buyer's order and hold its tickets while they pay. The ticket type row is locked while counting,
 * so two buyers can't both take the last tickets, and a coupon's row the same way for its last use.
 */
class PlaceOrder
{
    /**
     * @param  array{name: string, email: string, phone: ?string, marketing_consent: bool}  $buyer
     * @param  array<string, string>|null  $attribution
     *
     * @throws TicketsUnavailable
     * @throws CouponUnavailable
     */
    public function handle(TicketType $ticketType, int $quantity, array $buyer, ?string $visitorId = null, ?array $attribution = null, ?string $couponCode = null): Order
    {
        $order = DB::transaction(function () use ($ticketType, $quantity, $buyer, $visitorId, $attribution, $couponCode): Order {
            $ticketType = TicketType::query()->onSale()->lockForUpdate()->find($ticketType->id)
                ?? throw TicketsUnavailable::notOnSale();

            if ($quantity > $ticketType->maxQuantity()) {
                throw TicketsUnavailable::notEnough($ticketType->ticketsLeft());
            }

            $coupon = filled($couponCode) ? $this->lockCoupon($couponCode, $ticketType) : null;
            $discount = $coupon?->discountFor($ticketType->unitPrice(), $quantity)->amount ?? 0;

            return $ticketType->orders()->create([
                'quantity' => $quantity,
                'unit_price' => $ticketType->price,
                'amount' => $ticketType->unitPrice()->times($quantity)->amount - $discount,
                'coupon_id' => $coupon?->id,
                'discount_amount' => $discount,
                'currency' => $ticketType->currency,
                'buyer_name' => $buyer['name'],
                'buyer_email' => $buyer['email'],
                'buyer_phone' => $buyer['phone'],
                'marketing_consent' => $buyer['marketing_consent'],
                'visitor_id' => $visitorId,
                'attribution' => $attribution,
                'expires_at' => now()->addMinutes(config('payments.checkout_expires_in_minutes')),
            ]);
        });

        OrderPlaced::dispatch($order);

        return $order;
    }

    /**
     * The coupon with this code, locked while its uses are counted.
     *
     * @throws CouponUnavailable
     */
    private function lockCoupon(string $code, TicketType $ticketType): Coupon
    {
        $coupon = Coupon::query()->withCode($code)->lockForUpdate()->first() ?? throw CouponUnavailable::notFound();
        $problem = $coupon->whyUnusableOn($ticketType);

        if ($problem !== null) {
            throw $problem;
        }

        return $coupon;
    }
}
