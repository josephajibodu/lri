<?php

namespace App\Actions\Checkout;

use App\Events\OrderPlaced;
use App\Exceptions\CouponUnavailable;
use App\Exceptions\TicketsUnavailable;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Create a buyer's order and hold its tickets while they pay. The ticket type row is locked while counting,
 * so two buyers can't both take the last tickets, and a coupon's row the same way for its last use. Holding tickets
 * costs nothing, so no one person or connection can hold many unpaid ones, or more than one unpaid use of a limited
 * coupon, and that way can't stop everyone else buying.
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
    public function handle(TicketType $ticketType, int $quantity, array $buyer, ?string $visitorId = null, ?array $attribution = null, ?string $couponCode = null, ?string $ipAddress = null): Order
    {
        $order = DB::transaction(function () use ($ticketType, $quantity, $buyer, $visitorId, $attribution, $couponCode, $ipAddress): Order {
            $ticketType = TicketType::query()->onSale()->lockForUpdate()->find($ticketType->id)
                ?? throw TicketsUnavailable::notOnSale();

            if ($quantity > $ticketType->maxQuantity()) {
                throw TicketsUnavailable::notEnough($ticketType->ticketsLeft());
            }

            $this->refuseTooManyUnpaidTickets($quantity, $buyer['email'], $ipAddress);

            $coupon = filled($couponCode) ? $this->lockCoupon($couponCode, $ticketType, $buyer['email'], $ipAddress) : null;
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
                'ip_address' => $ipAddress,
                'attribution' => $attribution,
                'expires_at' => now()->addMinutes(config('payments.checkout_expires_in_minutes')),
            ]);
        });

        OrderPlaced::dispatch($order);

        return $order;
    }

    /**
     * Refuse more tickets for a buyer or connection already holding plenty without paying. Someone holding none can
     * always place one order, however big it's allowed to be.
     *
     * @throws TicketsUnavailable
     */
    private function refuseTooManyUnpaidTickets(int $quantity, string $email, ?string $ipAddress): void
    {
        $unpaidTickets = fn (string $column, ?string $value): int => $value === null ? 0 : (int) Order::query()->unpaidHold()->where($column, $value)->sum('quantity');
        $isOver = fn (int $held, int $limit): bool => $held > 0 && $held + $quantity > $limit;

        if ($isOver($unpaidTickets('buyer_email', $email), config('payments.unpaid_limits.tickets_per_buyer'))
            || $isOver($unpaidTickets('ip_address', $ipAddress), config('payments.unpaid_limits.tickets_per_connection'))) {
            throw TicketsUnavailable::tooManyUnpaid();
        }
    }

    /**
     * The coupon with this code, locked while its uses are counted. A buyer or connection already holding an unpaid
     * use of a limited coupon can't take another, so unpaid orders can't use up what's left of it.
     *
     * @throws CouponUnavailable
     */
    private function lockCoupon(string $code, TicketType $ticketType, string $email, ?string $ipAddress): Coupon
    {
        $coupon = Coupon::query()->withCode($code)->lockForUpdate()->first() ?? throw CouponUnavailable::notFound();
        $problem = $coupon->whyUnusableOn($ticketType);

        if ($problem !== null) {
            throw $problem;
        }

        $alreadyHeld = $coupon->max_orders !== null && $coupon->orders()->unpaidHold()
            ->where(fn (Builder $query) => $query->where('buyer_email', $email)->when($ipAddress, fn (Builder $query) => $query->orWhere('ip_address', $ipAddress)))
            ->exists();

        if ($alreadyHeld) {
            throw CouponUnavailable::alreadyHeld();
        }

        return $coupon;
    }
}
