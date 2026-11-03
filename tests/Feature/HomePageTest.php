<?php

use Carbon\CarbonImmutable;

test('the home page shows the next community run from config', function () {
    config()->set('lri.next_run', [
        'title' => 'Harmattan Hill Run',
        'starts_at' => '2026-12-05 06:30',
        'timezone' => 'Africa/Lagos',
        'venue' => 'Agodi Gardens',
        'address' => 'Secretariat Road',
        'map_url' => 'https://maps.example.com/agodi',
        'note' => 'Bring water and a friend.',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeText('Sat 5 Dec, 6:30 AM at Agodi Gardens')
        ->assertSeeText('Harmattan Hill Run')
        ->assertSeeText('Agodi Gardens, Secretariat Road')
        ->assertSeeText('Bring water and a friend.')
        ->assertSee('href="https://maps.example.com/agodi"', false)
        ->assertSee('data-starts-at="2026-12-05T06:30:00+01:00"', false);
});

test('the home page links to the member form and social accounts from config', function () {
    config()->set('lri.links.member_form', 'https://forms.example.com/join-lri');
    config()->set('lri.links.instagram', 'https://instagram.example.com/lri');
    config()->set('lri.links.tiktok', 'https://tiktok.example.com/@lri');
    config()->set('lri.links.strava', 'https://strava.example.com/clubs/lri');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="https://forms.example.com/join-lri"', false)
        ->assertSee('href="https://instagram.example.com/lri"', false)
        ->assertSee('href="https://tiktok.example.com/@lri"', false)
        ->assertSee('href="https://strava.example.com/clubs/lri"', false)
        ->assertSeeText('Follow @letsrunibadan');
});

test('the home page features the configured Instagram posts in order', function () {
    config()->set('lri.instagram_posts', [
        'https://www.instagram.com/reel/RunRaveReel/',
        'https://www.instagram.com/p/HangoutPhoto/',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('id="instagram"', false)
        ->assertSeeInOrder([
            'data-instgrm-permalink="https://www.instagram.com/reel/RunRaveReel/"',
            'data-instgrm-permalink="https://www.instagram.com/p/HangoutPhoto/"',
        ], false);
});

test('the Instagram section is hidden when no posts are configured', function () {
    config()->set('lri.instagram_posts', []);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('id="instagram"', false)
        ->assertDontSee('data-instgrm-permalink', false);
});

test('a run from each tribe’s meeting point links to the tribes instead of a map', function () {
    config()->set('lri.next_run', [
        'title' => 'Saturday Tribe Run',
        'starts_at' => '2026-12-05 06:30',
        'timezone' => 'Africa/Lagos',
        'venue' => 'Your tribe’s meeting point',
        'address' => null,
        'map_url' => null,
        'note' => 'Your tribe posts the route in its group.',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeText('Sat 5 Dec, 6:30 AM at Your tribe’s meeting point')
        ->assertSee('href="#tribes"', false)
        ->assertDontSeeText('Open in Maps');
});

test('the home page features the silent disco run until it starts', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 12:00', 'Africa/Lagos'));
    config()->set('lri.disco_run.starts_at', '2026-12-11 18:00');
    config()->set('lri.disco_run.venue', 'Bodija, Ibadan');
    config()->set('lri.disco_run.price', '₦10,000');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('id="silent-disco"', false)
        ->assertSee('data-starts-at="2026-12-11T18:00:00+01:00"', false)
        ->assertSeeText('Bodija, Ibadan')
        ->assertSeeText('₦10,000')
        ->assertSee('href="'.route('disco-run').'#tickets"', false);
});

test('the home page drops the silent disco run once it has started', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-11 18:01', 'Africa/Lagos'));
    config()->set('lri.disco_run.starts_at', '2026-12-11 18:00');

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('id="silent-disco"', false);
});
