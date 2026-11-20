<?php

use App\Enums\CheckoutStep;
use App\Http\Middleware\IdentifyVisitor;
use App\Models\CheckoutEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const InstagramBrowser = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 341.0.0.0.0 (iPhone14,7; iOS 17_5; en_GB)';

const FacebookBrowser = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/470.0.0.40.97;FBBV/601712237]';

const TikTokBrowser = 'Mozilla/5.0 (Linux; Android 13; TECNO KJ5) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/120.0.0.0 Mobile Safari/537.36 musical_ly_2023203030 BytedanceWebview/d8a21c6';

/**
 * Where the latest look at the ticket page was credited.
 */
function creditedSource(): ?string
{
    return CheckoutEvent::query()->where('step', CheckoutStep::TicketPageViewed)->latest('id')->firstOrFail()->properties['source'] ?? null;
}

/**
 * What a response remembered about where its visitor came from.
 *
 * @return array<string, string>|null
 */
function rememberedAttribution(TestResponse $response): ?array
{
    return json_decode((string) $response->getCookie(IdentifyVisitor::AttributionCookie)?->getValue(), true);
}

test('a visit is credited to the app whose browser opened the link, even when the app hides the site', function (string $userAgent, string $source) {
    $this->withHeader('User-Agent', $userAgent)->get(route('disco-run'))->assertOk();

    expect(creditedSource())->toBe($source);
})->with([
    'Instagram' => [InstagramBrowser, 'instagram'],
    'Facebook' => [FacebookBrowser, 'facebook'],
    'TikTok' => [TikTokBrowser, 'tiktok'],
]);

test('Meta’s click id on a link tapped in Instagram is credited to Instagram, not to a Facebook ad', function () {
    $response = $this->withHeader('User-Agent', InstagramBrowser)->get(route('disco-run', ['fbclid' => 'PAZXh0bgNhZW0']));

    expect(creditedSource())->toBe('instagram')
        ->and(rememberedAttribution($response))->not->toHaveKey('medium');
});

test('Meta’s click id elsewhere is credited to Facebook, without calling it paid', function () {
    $response = $this->get(route('disco-run', ['fbclid' => 'IwAR3x']));

    expect(creditedSource())->toBe('facebook')
        ->and(rememberedAttribution($response))->not->toHaveKey('medium');
});

test('an ad’s click id is credited to its network as paid', function (string $clickId, string $source) {
    $response = $this->get(route('disco-run', [$clickId => 'abc123']));

    expect(creditedSource())->toBe($source)
        ->and(rememberedAttribution($response))->toMatchArray(['source' => $source, 'medium' => 'paid']);
})->with([
    'Google' => ['gclid', 'google'],
    'Google on an iPhone' => ['gbraid', 'google'],
    'TikTok' => ['ttclid', 'tiktok'],
]);

test('a visit from a known site is credited to its place, whichever of its addresses sent it', function (string $referrer, string $source) {
    $this->withHeader('Referer', $referrer)->get(route('disco-run'));

    expect(creditedSource())->toBe($source);
})->with([
    'Google in Nigeria' => ['https://www.google.com.ng/', 'google'],
    'Instagram' => ['https://l.instagram.com/?u=https%3A%2F%2Fletsrunibadan.com', 'instagram'],
    'Facebook on a phone' => ['https://lm.facebook.com/', 'facebook'],
    'X' => ['https://t.co/a1b2c3', 'x'],
    'the WhatsApp app on Android' => ['android-app://com.whatsapp/', 'whatsapp'],
    'Gmail' => ['https://mail.google.com/', 'email'],
]);

test('a link’s own tags win over the app it was opened in', function () {
    $this->withHeader('User-Agent', InstagramBrowser)->get(route('disco-run', ['utm_source' => 'WhatsApp', 'utm_medium' => 'group']));

    expect(creditedSource())->toBe('whatsapp');
});

test('a visitor who lands on the home page from Instagram is credited to Instagram when they reach the tickets', function () {
    $home = $this->withHeader('Referer', 'https://l.instagram.com/')->get(route('home'))->assertOk();

    $this->withCookie(IdentifyVisitor::AttributionCookie, $home->getCookie(IdentifyVisitor::AttributionCookie)->getValue())
        ->withHeader('Referer', route('home'))
        ->get(route('disco-run'));

    expect(creditedSource())->toBe('instagram');
});

test('a visit with nothing to say where it came from is left uncredited', function () {
    $response = $this->get(route('disco-run'));

    expect(CheckoutEvent::query()->sole()->properties)->not->toHaveKey('source')
        ->and($response->getCookie(IdentifyVisitor::AttributionCookie))->toBeNull();
});
