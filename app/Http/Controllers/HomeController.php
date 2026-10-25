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
        $nextRun['starts_at'] = CarbonImmutable::parse($nextRun['starts_at'], $nextRun['timezone']);

        return view('home', [
            'nextRun' => $nextRun,
            'structuredData' => $this->structuredData($nextRun),
        ]);
    }

    /**
     * Describe the club, the site and the next run in schema.org terms for search engines.
     * The run is only listed while it is still to come.
     *
     * @param  array{title: string, starts_at: CarbonImmutable, venue: string, address: string, note: string}  $nextRun
     * @return array{'@context': string, '@graph': list<array<string, mixed>>}
     */
    private function structuredData(array $nextRun): array
    {
        $site = config('lri.site');
        $homeUrl = route('home');
        $clubId = $homeUrl.'#club';
        $address = [
            '@type' => 'PostalAddress',
            'addressLocality' => $site['locality'],
            'addressRegion' => $site['region'],
            'addressCountry' => $site['country'],
        ];

        $graph = [
            [
                '@type' => 'SportsClub',
                '@id' => $clubId,
                'name' => $site['name'],
                'alternateName' => $site['alternate_name'],
                'url' => $homeUrl,
                'logo' => asset('images/lri-logo.png'),
                'image' => asset('images/og-image.png'),
                'description' => $site['description'],
                'slogan' => $site['slogan'],
                'sport' => 'Running',
                'address' => $address,
                'areaServed' => ['@type' => 'City', 'name' => $site['locality']],
                'sameAs' => [
                    config('lri.links.instagram'),
                    config('lri.links.tiktok'),
                    config('lri.links.strava'),
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $homeUrl.'#website',
                'name' => $site['name'],
                'alternateName' => $site['alternate_name'],
                'url' => $homeUrl,
                'inLanguage' => 'en',
                'publisher' => ['@id' => $clubId],
            ],
        ];

        if ($nextRun['starts_at']->isFuture()) {
            $graph[] = [
                '@type' => 'SportsEvent',
                'name' => $nextRun['title'],
                'description' => $nextRun['note'],
                'startDate' => $nextRun['starts_at']->toIso8601String(),
                'eventStatus' => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'isAccessibleForFree' => true,
                'image' => asset('images/og-image.png'),
                'url' => $homeUrl.'#next',
                'location' => [
                    '@type' => 'Place',
                    'name' => $nextRun['venue'],
                    'address' => ['streetAddress' => $nextRun['address'], ...$address],
                ],
                'organizer' => ['@id' => $clubId],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => 0,
                    'priceCurrency' => 'NGN',
                    'availability' => 'https://schema.org/InStock',
                    'url' => $homeUrl.'#join',
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }
}
