<?php

use App\Models\Coupon;
use App\Models\Order;
use App\Models\PaymentChannel;
use App\Models\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00', 'Africa/Lagos'));
    config()->set('lri.disco_run.title', 'Silent Disco Run');
    config()->set('lri.disco_run.starts_at', '2026-12-11 18:00');
    $this->ticketType = TicketType::factory()->create(['price' => 1_000_000]);
    PaymentChannel::factory()->create();
});

test('while a sale runs, the ticket page counts down to its end at the top and under the price', function () {
    Coupon::factory()->automatic()->create([
        'code' => 'BLACKFRIDAY',
        'discount_value' => 30,
        'ends_at' => now()->addDays(3)->addHours(4)->addMinutes(16)->addSeconds(34),
    ]);

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSeeInOrder(['class="sale-bar"', 'data-sale-ends-in="274594"', '30% off', 'class="site-head'], false)
        ->assertSeeText('Silent Disco Run tickets, now ₦7,000 instead of ₦10,000')
        ->assertSeeText('Ends Sat 28 Nov at 2:16 PM')
        ->assertSeeInOrder(['<b>03</b><i>d</i>', '<b>04</b><i>h</i>', '<b>16</b><i>m</i>', '<b>34</b><i>s</i>'], false)
        ->assertSee('href="#tickets" aria-label="Get 30% off tickets"', false)
        ->assertSeeText('Sale price ends in 3d 04h 16m 34s')
        ->assertDontSee('is-last-day', false);
});

test('on its last day the sale ends “today” and the clock leaves the days out', function () {
    Coupon::factory()->automatic()->create(['ends_at' => CarbonImmutable::parse('2026-11-25 23:59', 'Africa/Lagos')->utc()]);

    $response = $this->get(route('disco-run'))
        ->assertOk()
        ->assertSee('class="sale-bar is-last-day"', false)
        ->assertSeeText('Ends today at 11:59 PM')
        ->assertSeeText('Sale price ends in 13h 59m 00s');

    expect($response->getContent())->toMatch('/data-sale-unit="d"\s+hidden/');
});

test('a sale with no end date shows without a clock', function () {
    Coupon::factory()->automatic()->create(['ends_at' => null]);

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSeeText('Silent Disco Run tickets, now ₦8,000')
        ->assertDontSee('data-sale-ends-in', false)
        ->assertDontSeeText('Sale price ends in');
});

test('a sale on a few tickets an order says so', function () {
    Coupon::factory()->automatic()->create(['max_tickets_per_order' => 2, 'ends_at' => now()->addDay()]);

    $this->get(route('disco-run'))->assertOk()->assertSeeText('On up to 2 tickets an order');
});

test('once the sale ends the ticket page is back to normal, keeping everything else at the top', function () {
    Coupon::factory()->automatic()->create(['code' => 'BLACKFRIDAY', 'ends_at' => now()->addHour()]);
    $this->get(route('disco-run'))->assertOk()->assertSee('class="sale-bar', false);

    $this->travel(61)->minutes();

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertDontSee('sale-bar', false)
        ->assertDontSee('instead of', false)
        ->assertDontSee('value="BLACKFRIDAY"', false)
        ->assertDontSeeText('Sale price ends in')
        ->assertSeeInOrder(['class="dr-hero"', '₦10,000', 'Headset included', 'Get your ticket ↓'], false);
});

test('no sale is shown while tickets can’t be bought', function (Closure $stopSales) {
    Coupon::factory()->automatic()->create(['ends_at' => now()->addDay()]);
    $stopSales($this->ticketType);

    $this->get(route('disco-run'))->assertOk()->assertDontSee('sale-bar', false);
    $this->get(route('home'))->assertOk()->assertDontSee('sale-bar', false);
})->with([
    'sold out' => [function (TicketType $ticketType) {
        $ticketType->update(['capacity' => 1]);
        Order::factory()->for($ticketType)->paid()->create(['quantity' => 1]);
    }],
    'no way to pay' => [fn () => PaymentChannel::query()->delete()],
]);

test('while a sale runs, its countdown takes the home page’s top bar and leads to the tickets', function () {
    Coupon::factory()->automatic()->create(['discount_value' => 30, 'ends_at' => now()->addDays(2)]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('class="sale-bar"', false)
        ->assertSeeText('Silent Disco Run tickets, now ₦7,000')
        ->assertSee('href="'.route('disco-run').'#tickets" aria-label="Get 30% off tickets"', false)
        ->assertDontSee('topbar-disco', false);
});

test('once the sale ends the home page’s top bar goes back to the event', function () {
    Coupon::factory()->automatic()->create(['ends_at' => now()->addHour()]);

    $this->travel(61)->minutes();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('sale-bar', false)
        ->assertSee('class="topbar topbar-disco"', false);
});
