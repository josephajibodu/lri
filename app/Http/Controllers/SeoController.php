<?php

namespace App\Http\Controllers;

use App\Support\SiteAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->view('seo.sitemap', ['urls' => [route('home'), route('founders'), route('pace-setters'), route('gallery'), route('disco-run')]])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Describe the site to phones that add it to the home screen, with icons from the bucket.
     */
    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => config('lri.site.name'),
            'short_name' => 'LRI',
            'description' => 'A free run club for runners, joggers and walkers in Ibadan, Oyo State.',
            'start_url' => '/',
            'display' => 'browser',
            'theme_color' => '#FFB000',
            'background_color' => '#EEECE7',
            'icons' => [
                ['src' => SiteAsset::url('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => SiteAsset::url('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ], headers: ['Content-Type' => 'application/manifest+json'], options: JSON_UNESCAPED_SLASHES);
    }

    /**
     * Send old addresses for images and icons over to the bucket. Emails already in people's inboxes
     * still point at them, and browsers and crawlers look for icons there.
     */
    public function redirectToBucket(Request $request): RedirectResponse
    {
        abort_if(blank(config('filesystems.disks.r2.url')), 404);

        return redirect()->away(SiteAsset::url($request->path()));
    }
}
