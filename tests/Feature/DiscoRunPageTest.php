<?php

test('the disco run page shows the run, its channels and the date window until a date is set', function () {
    config()->set('lri.disco_run.starts_at', null);
    config()->set('lri.disco_run.date_window', 'First two weeks of December');

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSee('<title>Silent Disco Run · Let’s Run Ibadan</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('disco-run').'">', false)
        ->assertSeeText('First two weeks of December')
        ->assertSeeText('Afro Heat')
        ->assertSeeText('Piano Hours')
        ->assertSeeText('Rewind');
});

test('the disco run page shows the start time once a date is set', function () {
    config()->set('lri.disco_run.starts_at', '2026-12-05 18:30');

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSeeText('Sat 5 Dec · 6:30 PM')
        ->assertDontSeeText('First two weeks of December');
});

test('the faq gives the date, time and venue once a date is set', function () {
    config()->set('lri.disco_run.starts_at', '2026-12-11 18:00');
    config()->set('lri.disco_run.venue', 'Bodija, Ibadan');

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSeeText('Friday 11 December at 6 PM in Bodija, Ibadan.');
});

test('the disco run page uses the flyer for link previews', function () {
    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.asset('images/disco-run/og-image.jpg').'">', false)
        ->assertSee('<meta name="twitter:image" content="'.asset('images/disco-run/og-image.jpg').'">', false);
});

test('the ticket says tickets open soon when no payment is set up', function () {
    config()->set('lri.disco_run.checkout_url', null);
    config()->set('lri.disco_run.embed_script', null);
    config()->set('lri.disco_run.embed_product', null);

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSeeText('Tickets open soon')
        ->assertDontSeeText('Pay for your ticket');
});

test('the ticket links to the payment page when a checkout link is set', function () {
    config()->set('lri.disco_run.checkout_url', 'https://pay.example.com/disco-run');
    config()->set('lri.disco_run.price', '₦10,000');

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSee('href="https://pay.example.com/disco-run"', false)
        ->assertSeeText('Pay for your ticket')
        ->assertSeeText('₦10,000')
        ->assertDontSeeText('Tickets open soon');
});

test('the ticket shows the embedded checkout in place of the link when one is set', function () {
    config()->set('lri.disco_run.checkout_url', 'https://pay.example.com/disco-run');
    config()->set('lri.disco_run.embed_script', 'https://checkout.example.com/embed.js');
    config()->set('lri.disco_run.embed_product', 'abc123');

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSee('<script src="https://checkout.example.com/embed.js" data-product="abc123"', false)
        ->assertDontSee('href="https://pay.example.com/disco-run"', false);
});

test('the site footer links to the disco run page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="'.route('disco-run').'"', false);
});

test('the disco run page lets people share the flyer on WhatsApp or download it', function () {
    config()->set('lri.disco_run.starts_at', '2026-12-11 18:00');
    config()->set('lri.disco_run.venue', 'Bodija, Ibadan');

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSee('src="'.asset('images/disco-run/flyer.jpg').'"', false)
        ->assertSee('href="'.asset('images/disco-run/flyer-full.jpg').'" download', false)
        ->assertSee('href="https://wa.me/?text='.rawurlencode('Silent Disco Run with Let’s Run Ibadan, Fri 11 Dec, 6 PM in Bodija, Ibadan. Headsets on, run and vibe, then rave. Get your ticket: '.route('disco-run')).'"', false);
});
