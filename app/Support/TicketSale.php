<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\TicketType;
use Carbon\CarbonImmutable;

/**
 * A sale every buyer of a ticket gets right now: a coupon that applies itself, what it does to one ticket's price,
 * and how long is left of it. Pages count it down so buyers can see the lower price won't last.
 */
final readonly class TicketSale
{
    public function __construct(public Coupon $coupon, public TicketType $ticketType) {}

    /**
     * The sale this coupon makes on the ticket, or null without a coupon or when it takes nothing off one ticket.
     */
    public static function from(?Coupon $coupon, TicketType $ticketType): ?self
    {
        $sale = $coupon ? new self($coupon, $ticketType) : null;

        return $sale && $sale->price()->amount < $ticketType->price ? $sale : null;
    }

    /**
     * What one ticket costs with the sale, worked out the same way as the ticket form.
     */
    public function price(): Money
    {
        $fullPrice = $this->ticketType->unitPrice();

        return Money::of($fullPrice->amount - $this->coupon->discountFor($fullPrice, 1)->amount, $fullPrice->currency);
    }

    /**
     * When the sale stops, in the event's timezone, or null when it runs until an organiser switches it off.
     */
    public function endsAt(): ?CarbonImmutable
    {
        return $this->coupon->ends_at?->toImmutable()->setTimezone($this->ticketType->eventDetails()['timezone'] ?? config('app.timezone'));
    }

    /**
     * When the sale stops, in words, such as "today at 11:59 PM" or "Sat 28 Nov at 11:59 PM".
     */
    public function endsInWords(): ?string
    {
        $endsAt = $this->endsAt();

        if ($endsAt === null) {
            return null;
        }

        $today = CarbonImmutable::now($endsAt->timezone);
        $day = match (true) {
            $endsAt->isSameDay($today) => 'today',
            $endsAt->isSameDay($today->addDay()) => 'tomorrow',
            default => $endsAt->format('D j M'),
        };

        return "{$day} at {$endsAt->format('g:i A')}";
    }

    /**
     * Whole seconds until the sale stops, or null when it has no end.
     */
    public function secondsLeft(): ?int
    {
        return $this->coupon->ends_at ? max(0, $this->coupon->ends_at->getTimestamp() - now()->getTimestamp()) : null;
    }

    /**
     * The time left split into days, hours, minutes and seconds for a countdown clock, or null when it has no end.
     *
     * @return array{d: int, h: int, m: int, s: int}|null
     */
    public function timeLeft(): ?array
    {
        $left = $this->secondsLeft();

        return $left === null ? null : [
            'd' => intdiv($left, 86400),
            'h' => intdiv($left % 86400, 3600),
            'm' => intdiv($left % 3600, 60),
            's' => $left % 60,
        ];
    }

    /**
     * The time left as a short countdown, such as "3d 04h 16m 34s", leaving the days out on the last day. The page's
     * script keeps it ticking in the same form.
     */
    public function countdown(): ?string
    {
        $left = $this->timeLeft();

        return $left === null ? null : ($left['d'] > 0 ? "{$left['d']}d " : '').sprintf('%02dh %02dm %02ds', $left['h'], $left['m'], $left['s']);
    }

    /**
     * How much of the sale is still to run, from 1 at its start to 0 at its end, or null when it has no end. A sale
     * with no start date started when it was created.
     */
    public function shareLeft(): ?float
    {
        $secondsLeft = $this->secondsLeft();

        if ($secondsLeft === null) {
            return null;
        }

        $startedAt = $this->coupon->starts_at ?? $this->coupon->created_at;
        $length = $this->coupon->ends_at->getTimestamp() - $startedAt->getTimestamp();

        return $length > 0 ? round(min(1, $secondsLeft / $length), 4) : 0.0;
    }

    /**
     * Whether less than a day of the sale is left.
     */
    public function isLastDay(): bool
    {
        return $this->secondsLeft() !== null && $this->secondsLeft() < 86400;
    }
}
