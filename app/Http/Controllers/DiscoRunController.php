<?php

namespace App\Http\Controllers;

use App\Models\PaymentChannel;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DiscoRunController extends Controller
{
    /**
     * Show the Silent Disco Run: what it is, how the night goes, and how to get a ticket.
     */
    public function __invoke(): View
    {
        $discoRun = config('lri.disco_run');

        $startsAt = $discoRun['starts_at']
            ? Carbon::parse($discoRun['starts_at'], $discoRun['timezone'])
            : null;

        return view('disco-run', [
            'discoRun' => $discoRun,
            'startsAt' => $startsAt,
            'when' => $startsAt?->format('D j M · g:i A') ?? $discoRun['date_window'],
            'ticketing' => $this->ticketing(),
        ]);
    }

    /**
     * What the ticket card offers: the ticket on sale and the ways to pay for it, or why it can't be bought yet.
     *
     * @return array{state: 'soon'|'sold_out'|'on_sale', ticketType: ?TicketType, channels: Collection<int, PaymentChannel>, maxQuantity: int}
     */
    private function ticketing(): array
    {
        $ticketType = TicketType::query()->onSale()->where('event', 'disco-run')->orderBy('sort_order')->first();
        $channels = $ticketType ? PaymentChannel::query()->offeredIn($ticketType->currency)->get() : new Collection;
        $maxQuantity = $ticketType?->maxQuantity() ?? 0;

        return [
            'state' => match (true) {
                $ticketType === null, $channels->isEmpty() => 'soon',
                $maxQuantity < 1 => 'sold_out',
                default => 'on_sale',
            },
            'ticketType' => $ticketType,
            'channels' => $channels,
            'maxQuantity' => $maxQuantity,
        ];
    }
}
