<?php

namespace App\Http\Controllers;

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
            'checkout' => $this->checkout($discoRun),
        ]);
    }

    /**
     * Work out how people pay: an embedded checkout, a payment link, or nothing yet.
     *
     * @param  array<string, mixed>  $discoRun
     * @return array{type: 'embed'|'link'|'soon', url: ?string, script: ?string, product: ?string}
     */
    private function checkout(array $discoRun): array
    {
        $type = match (true) {
            filled($discoRun['embed_script']) && filled($discoRun['embed_product']) => 'embed',
            filled($discoRun['checkout_url']) => 'link',
            default => 'soon',
        };

        return [
            'type' => $type,
            'url' => $discoRun['checkout_url'],
            'script' => $discoRun['embed_script'],
            'product' => $discoRun['embed_product'],
        ];
    }
}
