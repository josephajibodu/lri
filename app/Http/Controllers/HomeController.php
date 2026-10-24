<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the community home page.
     */
    public function __invoke(): View
    {
        $nextRun = config('lri.next_run');

        return view('home', [
            'nextRun' => [
                ...$nextRun,
                'starts_at' => CarbonImmutable::parse($nextRun['starts_at'], $nextRun['timezone']),
            ],
        ]);
    }
}
