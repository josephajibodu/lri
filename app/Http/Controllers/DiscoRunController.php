<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\RecordCheckoutStep;
use App\Enums\CheckoutStep;
use App\Http\Middleware\IdentifyVisitor;
use App\Support\EventTicketing;
use App\Support\TrafficSource;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DiscoRunController extends Controller
{
    /**
     * Show the Silent Disco Run: what it is, how the night goes, and how to get a ticket.
     */
    public function __invoke(Request $request, RecordCheckoutStep $recordCheckoutStep): View
    {
        $discoRun = config('lri.disco_run');
        $ticketing = EventTicketing::now('disco-run');

        if (! TrafficSource::isCrawler($request->userAgent())) {
            $attribution = IdentifyVisitor::attributionFor($request);

            $recordCheckoutStep->handle(CheckoutStep::TicketPageViewed, IdentifyVisitor::visitorId($request), properties: [
                'tickets' => $ticketing['state'],
                'source' => $attribution === null ? null : TrafficSource::of($attribution),
                'link' => $attribution['content'] ?? null,
                'campaign' => $attribution['campaign'] ?? null,
            ]);
        }

        $startsAt = $discoRun['starts_at']
            ? Carbon::parse($discoRun['starts_at'], $discoRun['timezone'])
            : null;

        return view('disco-run', [
            'discoRun' => $discoRun,
            'startsAt' => $startsAt,
            'when' => $startsAt?->format('D j M · g:i A') ?? $discoRun['date_window'],
            'ticketing' => $ticketing,
        ]);
    }
}
