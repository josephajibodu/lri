<?php

use App\Providers\AppServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

/**
 * @return array{'@context': string, '@graph': list<array<string, mixed>>}
 */
function homePageStructuredData(TestResponse $response): array
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $matches);

    return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
}

beforeEach(function () {
    config()->set('lri.next_run', [
        'title' => 'Harmattan Hill Run',
        'starts_at' => '2026-12-05 06:30',
        'timezone' => 'Africa/Lagos',
        'venue' => 'Agodi Gardens',
        'address' => 'Secretariat Road',
        'map_url' => 'https://maps.example.com/agodi',
        'note' => 'Bring water and a friend.',
    ]);
});

test('the home page tells search engines and link previews what the club is', function () {
    config()->set('lri.site.title', 'LRI | Run club in Ibadan');
    config()->set('lri.site.description', 'Saturday runs in Ibadan from 5 km.');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>LRI | Run club in Ibadan</title>', false)
        ->assertSee('<meta name="description" content="Saturday runs in Ibadan from 5 km.">', false)
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
        ->assertSee('<meta property="og:title" content="LRI | Run club in Ibadan">', false)
        ->assertSee('<meta property="og:image" content="'.asset('images/og-image.png').'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
});

test('the structured data lists the club and the next run while it is still to come', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Africa/Lagos'));

    $graph = collect(homePageStructuredData($this->get(route('home')))['@graph'])->keyBy('@type');

    expect($graph->keys()->all())->toBe(['SportsClub', 'WebSite', 'SportsEvent'])
        ->and($graph['SportsClub']['name'])->toBe("Let's Run Ibadan")
        ->and($graph['SportsClub']['sameAs'])->toBe([config('lri.links.instagram')])
        ->and($graph['SportsEvent']['name'])->toBe('Harmattan Hill Run')
        ->and($graph['SportsEvent']['startDate'])->toBe('2026-12-05T06:30:00+01:00')
        ->and($graph['SportsEvent']['location']['name'])->toBe('Agodi Gardens')
        ->and($graph['SportsEvent']['location']['address']['streetAddress'])->toBe('Secretariat Road')
        ->and($graph['SportsEvent']['location']['address']['addressLocality'])->toBe('Ibadan')
        ->and($graph['SportsEvent']['organizer'])->toBe(['@id' => $graph['SportsClub']['@id']]);
});

test('the structured data leaves the next run out once it has started', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-05 06:31', 'Africa/Lagos'));

    $types = collect(homePageStructuredData($this->get(route('home')))['@graph'])->pluck('@type');

    expect($types->all())->toBe(['SportsClub', 'WebSite']);
});

test('error pages ask search engines not to index them', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertDontSee('rel="canonical"', false);
});

test('robots.txt lets crawlers in and points them at the sitemap', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect($response->getContent())
        ->toContain("User-agent: *\nAllow: /")
        ->toContain('Sitemap: '.route('sitemap'));
});

test('links and the canonical address use https in production', function () {
    app()->detectEnvironment(fn () => 'production');
    (new AppServiceProvider(app()))->boot();

    expect(route('home'))->toStartWith('https://')
        ->and(route('sitemap'))->toStartWith('https://');
});

test('the sitemap lists the home page', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $sitemap = simplexml_load_string($response->getContent());

    expect((string) $sitemap->url[0]->loc)->toBe(route('home'))
        ->and($sitemap->url)->toHaveCount(1);
});
