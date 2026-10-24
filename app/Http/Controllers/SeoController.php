<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SeoController extends Controller
{
    /**
     * Let every crawler in and point it at the sitemap.
     */
    public function robots(): Response
    {
        $rules = implode("\n", [
            'User-agent: *',
            'Allow: /',
            '',
            'Sitemap: '.route('sitemap'),
        ]);

        return response($rules."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * List the pages search engines should index.
     */
    public function sitemap(): Response
    {
        return response()
            ->view('seo.sitemap', ['urls' => [route('home')]])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
