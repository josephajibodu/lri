<?php

use App\Mail\TicketsMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the tickets email gives each ticket code, the event and a link to the receipt', function () {
    config()->set('lri.disco_run.title', 'Ibadan Silent Disco Run');
    config()->set('lri.disco_run.starts_at', '2026-12-11 18:00');
    config()->set('lri.disco_run.venue', 'Bodija, Ibadan');

    $order = Order::factory()->paid()->for(TicketType::factory()->state(['event' => 'disco-run']))
        ->create(['buyer_name' => 'Ada Bello', 'quantity' => 2, 'unit_price' => 1_000_000, 'amount' => 2_000_000]);
    $tickets = Ticket::factory()->count(2)->for($order)->create(['ticket_type_id' => $order->ticket_type_id]);

    $mail = new TicketsMail($order->load('tickets'));

    $mail->assertHasSubject("Your Ibadan Silent Disco Run tickets · {$order->reference}")
        ->assertSeeInHtml('You’re in, Ada.')
        ->assertSeeInHtml($tickets[0]->code)
        ->assertSeeInHtml($tickets[1]->code)
        ->assertSeeInText('Friday 11 December, 6:00 PM')
        ->assertSeeInText('Bodija, Ibadan')
        ->assertSeeInText("Ticket 1: {$tickets[0]->code}")
        ->assertSeeInText("Ticket 2: {$tickets[1]->code}")
        ->assertSeeInText('2 × ₦10,000 = ₦20,000')
        ->assertSeeInHtml(e($order->receiptUrl()), false);
});

test('the tickets email shows the club logo and the event banner and accent from the config', function () {
    config()->set('lri.disco_run.title', 'Ibadan Silent Disco Run');
    config()->set('lri.disco_run.email_banner', 'images/disco-run/email-banner.jpg');
    config()->set('lri.disco_run.email_accent', '#123456');

    $order = Order::factory()->paid()->for(TicketType::factory()->state(['event' => 'disco-run']))
        ->create(['quantity' => 2]);
    Ticket::factory()->count(2)->for($order)->create(['ticket_type_id' => $order->ticket_type_id]);

    $mail = new TicketsMail($order->load('tickets'));

    $mail->assertSeeInHtml(asset('images/lri-logo-email.png'))
        ->assertSeeInHtml('alt="Let\'s Run Ibadan"', false)
        ->assertSeeInHtml(asset('images/disco-run/email-banner.jpg'))
        ->assertSeeInHtml('alt="Ibadan Silent Disco Run"', false)
        ->assertSeeInHtml('border-right: 1px dashed #123456', false)
        ->assertSeeInHtml('Your ticket codes and receipt for the Ibadan Silent Disco Run.');
});

test('the tickets email falls back to the ticket name and club colours when the event has no details', function () {
    $order = Order::factory()->paid()->for(TicketType::factory()->state(['event' => 'no-such-event', 'name' => 'Community Run ticket']))
        ->create(['quantity' => 1]);
    Ticket::factory()->for($order)->create(['ticket_type_id' => $order->ticket_type_id]);

    $mail = new TicketsMail($order->load('tickets'));

    $mail->assertSeeInHtml('Your ticket code and receipt for the Community Run ticket.')
        ->assertSeeInHtml('border-right: 1px dashed #FEB203', false)
        ->assertDontSeeInHtml('class="banner"', false)
        ->assertDontSeeInText('When:')
        ->assertDontSeeInText('Where:');
});
