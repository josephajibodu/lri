<?php

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\PaymentChannel;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

test('the ticket says tickets open soon until a ticket is on sale', function () {
    PaymentChannel::factory()->create();
    TicketType::factory()->inactive()->create();

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSeeText('Tickets open soon')
        ->assertDontSee('action="'.route('orders.store').'"', false);
});

test('the ticket says tickets open soon while there is no way to pay', function () {
    TicketType::factory()->create();
    PaymentChannel::factory()->disabled()->create();

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSeeText('Tickets open soon');
});

test('the ticket takes orders with the featured way to pay up front and the others folded away', function () {
    TicketType::factory()->create(['slug' => 'disco-run-standard', 'price' => 1_000_000]);
    $transfer = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->featured()->create(['description' => 'Send from any Nigerian bank app.']);
    $card = PaymentChannel::factory()->method(PaymentMethod::Card)->create();

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSee('action="'.route('orders.store').'"', false)
        ->assertSee('name="ticket_type" value="disco-run-standard"', false)
        ->assertSeeText('₦10,000 a ticket')
        ->assertSeeInOrder(['Pay with', 'Bank transfer', 'Send from any Nigerian bank app.', 'More ways to pay', 'Card'])
        ->assertSee('name="channel" value="'.$transfer->id.'"', false)
        ->assertSee('name="channel" value="'.$card->id.'"', false)
        ->assertDontSeeText('Tickets open soon');
});

test('the header sends people to the ticket while tickets are on sale', function () {
    TicketType::factory()->create();
    PaymentChannel::factory()->create();

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSee('href="#tickets">Get your ticket</a>', false)
        ->assertDontSeeText('Join LRI');
});

test('the header keeps its join link while tickets can’t be bought', function () {
    PaymentChannel::factory()->create();
    TicketType::factory()->inactive()->create();

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSeeText('Join LRI')
        ->assertDontSee('href="#tickets">Get your ticket</a>', false);
});

test('the browser bar matches the page’s header instead of the site’s yellow', function () {
    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSee('<meta name="theme-color" content="#EEECE7">', false);
});

test('the ticket offers no more tickets than are left', function () {
    $ticketType = TicketType::factory()->limitedTo(5)->create(['max_per_order' => 10]);
    PaymentChannel::factory()->create();
    Order::factory()->for($ticketType)->paid()->create(['quantity' => 3]);

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSee('name="quantity" type="number" inputmode="numeric" min="1" max="2"', false);
});

test('the ticket says sold out once every ticket is taken', function () {
    $ticketType = TicketType::factory()->limitedTo(2)->create();
    PaymentChannel::factory()->create();
    Order::factory()->for($ticketType)->create(['quantity' => 2, 'expires_at' => now()->addMinutes(30)]);

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSeeText('Sold out')
        ->assertDontSee('action="'.route('orders.store').'"', false);
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
