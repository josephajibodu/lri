<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class GalleryController extends Controller
{
    /**
     * Show every photo from the club's runs and events.
     */
    public function __invoke(): View
    {
        return view('gallery', [
            'photos' => config('gallery.photos'),
        ]);
    }
}
