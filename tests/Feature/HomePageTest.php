<?php

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
