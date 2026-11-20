<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\PaymentChannel;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Collection;

/**
 * What an event's ticket card offers right now: the ticket on sale, the ways to pay for it and any sale every buyer
 * gets, or why it can't be bought yet. Its ticket page and the home page read it the same way.
 */
final class EventTicketing
{
    /**
     * @param  string  $event  The ticket types' event, such as "disco-run".
     * @return array{state: 'soon'|'sold_out'|'on_sale', ticketType: ?TicketType, channels: Collection<int, PaymentChannel>, maxQuantity: int, automaticCoupon: ?Coupon, sale: ?TicketSale}
     */
    public static function now(string $event): array
    {
        $ticketType = TicketType::query()->onSale()->where('event', $event)->orderBy('sort_order')->first();
        $channels = $ticketType ? PaymentChannel::query()->offeredIn($ticketType->currency)->get() : new Collection;
        $maxQuantity = $ticketType?->maxQuantity() ?? 0;
        $automaticCoupon = $ticketType ? Coupon::automaticFor($ticketType) : null;

        $state = match (true) {
            $ticketType === null, $channels->isEmpty() => 'soon',
            $maxQuantity < 1 => 'sold_out',
            default => 'on_sale',
        };

        return [
            'state' => $state,
            'ticketType' => $ticketType,
            'channels' => $channels,
            'maxQuantity' => $maxQuantity,
            'automaticCoupon' => $automaticCoupon,
            'sale' => $state === 'on_sale' ? TicketSale::from($automaticCoupon, $ticketType) : null,
        ];
    }
}
