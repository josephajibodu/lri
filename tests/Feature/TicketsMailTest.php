<?php

use App\Mail\TicketsMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the tickets email gives each ticket code, the event and a link to the order', function () {
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
        ->assertSeeInText('2 × ₦10,000 = ₦20,000')
        ->assertSeeInHtml(e($order->statusUrl()), false);
});
