<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\RecordCheckoutStep;
use App\Enums\CheckoutStep;
use App\Http\Middleware\IdentifyVisitor;
use App\Support\EventTicketing;
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

        if (! $this->isBot($request)) {
            $recordCheckoutStep->handle(CheckoutStep::TicketPageViewed, IdentifyVisitor::visitorId($request), properties: [
                'tickets' => $ticketing['state'],
                'source' => IdentifyVisitor::attributionFor($request)['source'] ?? IdentifyVisitor::attributionFor($request)['referrer'] ?? null,
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

    /**
     * Whether the request is a crawler or a link preview (WhatsApp, Instagram and the like), which would inflate the page views.
     */
    private function isBot(Request $request): bool
    {
        return (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|discord|curl|wget|python|headless/i', (string) $request->userAgent());
    }
}
