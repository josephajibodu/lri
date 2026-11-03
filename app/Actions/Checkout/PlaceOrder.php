<?php

namespace App\Actions\Checkout;

use App\Exceptions\TicketsUnavailable;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

/**
 * Create a buyer's order and hold its tickets while they pay. The ticket type row is locked while counting,
 * so two buyers can't both take the last tickets.
 */
class PlaceOrder
{
    /**
     * @param  array{name: string, email: string, phone: ?string, marketing_consent: bool}  $buyer
     * @param  array<string, string>|null  $attribution
     *
     * @throws TicketsUnavailable
     */
    public function handle(TicketType $ticketType, int $quantity, array $buyer, ?string $visitorId = null, ?array $attribution = null): Order
    {
        return DB::transaction(function () use ($ticketType, $quantity, $buyer, $visitorId, $attribution): Order {
            $ticketType = TicketType::query()->onSale()->lockForUpdate()->find($ticketType->id)
                ?? throw TicketsUnavailable::notOnSale();

            if ($quantity > $ticketType->maxQuantity()) {
                throw TicketsUnavailable::notEnough($ticketType->ticketsLeft());
            }

            return $ticketType->orders()->create([
                'quantity' => $quantity,
                'unit_price' => $ticketType->price,
                'amount' => $ticketType->unitPrice()->times($quantity)->amount,
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
    }
}
