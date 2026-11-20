<?php

namespace App\Http\Controllers;

use App\Models\TrackedLink;
use App\Support\TrafficSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrackedLinkController extends Controller
{
    /**
     * Count the tap and send the visitor on with the link's tags, so their visit and any order are credited to where
     * the link was shared. Link previews and crawlers are sent on without being counted.
     */
    public function __invoke(Request $request, TrackedLink $trackedLink): RedirectResponse
    {
        if (! TrafficSource::isCrawler($request->userAgent())) {
            $trackedLink->recordClick();
        }

        return redirect()->to($trackedLink->targetUrl());
    }
}
