<?php

use App\Models\AdminInvitation;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the email previews page lists every email and shows the first one', function () {
    $this->get('/mail-previews')
        ->assertOk()
        ->assertSeeText('Tickets, one ticket')
        ->assertSeeText('Tickets, several tickets')
        ->assertSeeText('Your Ibadan Silent Disco Run ticket · LRI-7K3Q9P')
        ->assertSee(route('mail-previews.show', 'tickets'), false);
});

test('the email previews page shows the email at the chosen screen size and keeps it when switching emails', function () {
    $this->get('/mail-previews/ticket?device=mobile')
        ->assertOk()
        ->assertSee('data-width="390"', false)
        ->assertSee(route('mail-previews.index', ['preview' => 'tickets', 'device' => 'mobile']), false);

    $this->get('/mail-previews/ticket?device=tablet')
        ->assertOk()
        ->assertSee('data-width="768"', false);
});

test('the email previews page falls back to desktop for an unknown screen size', function () {
    $this->get('/mail-previews?device=watch')
        ->assertOk()
        ->assertDontSee('data-width="', false);

    $this->get('/mail-previews?device[]=mobile')
        ->assertOk()
        ->assertDontSee('data-width="', false);
});

test('each email renders with sample data and saves nothing', function () {
    $this->get('/mail-previews/tickets/render')
        ->assertOk()
        ->assertSeeText('You’re in, Ada.')
        ->assertSeeText('7K3Q-9PXM')
        ->assertDontSeeText('H4TW-2NRC');

    $this->get('/mail-previews/tickets-several/render')
        ->assertOk()
        ->assertSeeText('3 tickets')
        ->assertSeeText('P8LD-6VKE')
        ->assertSeeText('3 × ₦10,000 = ₦30,000');

    expect(Order::count())->toBe(0);
});

test('the admin invitation can be previewed with sample data and saves nothing', function () {
    $this->get('/mail-previews/admin-invitation')
        ->assertOk()
        ->assertSeeText('Invitation to the admin');

    $this->get('/mail-previews/admin-invitation/render')
        ->assertOk()
        ->assertSeeText('You’re invited to join the team')
        ->assertSeeText('Joseph Ajibodu')
        ->assertSeeText('Headset desk')
        ->assertSeeText('Accept invitation')
        ->assertSeeText('Decline this invitation');

    expect(AdminInvitation::count())->toBe(0);
});

test('the links in the previewed invitation open sample accept and decline pages that can’t be submitted', function () {
    $this->get('/mail-previews/admin-invitation/render')
        ->assertOk()
        ->assertSee(route('mail-previews.invitation-page', 'accept'), false)
        ->assertSee(route('mail-previews.invitation-page', 'decline'), false);

    $this->get(route('mail-previews.invitation-page', 'accept'))
        ->assertOk()
        ->assertSeeText('Preview only')
        ->assertSeeText('Joseph Ajibodu invited you')
        ->assertSee('type="submit" disabled', false);

    $this->get(route('mail-previews.invitation-page', 'decline'))
        ->assertOk()
        ->assertSeeText('Preview only')
        ->assertSeeText('Decline this invitation?')
        ->assertSee('type="submit" disabled', false);

    $this->get('/mail-previews/admin-invitation/closed')->assertNotFound();

    expect(AdminInvitation::count())->toBe(0);
});

test('an unknown email is not found', function () {
    $this->get('/mail-previews/nope')->assertNotFound();
    $this->get('/mail-previews/nope/render')->assertNotFound();
});
