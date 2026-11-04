<?php

use App\Mail\TicketMail;
use App\Mail\TicketsMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    $this->order = Order::factory()->paid()->for(TicketType::factory())
        ->create(['buyer_name' => 'Ada Bello', 'buyer_email' => 'ada@example.com', 'quantity' => 2]);
    $this->buyersTicket = Ticket::factory()->for($this->order)
        ->create(['ticket_type_id' => $this->order->ticket_type_id, 'holder_name' => 'Ada Bello', 'holder_email' => 'ada@example.com']);
    $this->friendsTicket = Ticket::factory()->for($this->order)
        ->create(['ticket_type_id' => $this->order->ticket_type_id]);
});

/**
 * The buyer saves a name and email for one of their tickets from the receipt.
 *
 * @param  array<string, string>  $details
 */
function nameFromReceipt(Order $order, Ticket $ticket, array $details): TestResponse
{
    return test()->from($order->receiptUrl())
        ->put(route('receipts.tickets.update', [$order->receipt_token, $ticket]), $details);
}

test('the receipt asks who each blank ticket is for and offers its link to send', function () {
    $this->get($this->order->receiptUrl())
        ->assertOk()
        ->assertSeeText('Who is this ticket for?')
        ->assertSeeText('Ada Bello · ada@example.com')
        ->assertSee($this->friendsTicket->claimUrl(), false)
        ->assertDontSee($this->buyersTicket->claimUrl(), false);
});

test('the buyer names a ticket from the receipt and its holder is emailed their ticket', function () {
    nameFromReceipt($this->order, $this->friendsTicket, ['name' => 'Tunde Okafor', 'email' => 'tunde@example.com'])
        ->assertRedirect($this->order->receiptUrl().'#ticket-'.$this->friendsTicket->id)
        ->assertSessionHas('status', 'Saved. We’ve emailed Tunde Okafor their ticket.');

    expect($this->friendsTicket->fresh())
        ->holder_name->toBe('Tunde Okafor')
        ->holder_email->toBe('tunde@example.com');

    Mail::assertQueued(TicketMail::class, fn (TicketMail $mail): bool => $mail->hasTo('tunde@example.com') && $mail->ticket->is($this->friendsTicket));
});

test('the buyer is not emailed again for their own ticket, nor a holder for a name-only change', function () {
    nameFromReceipt($this->order, $this->buyersTicket, ['name' => 'Ada B.', 'email' => 'ADA@example.com'])->assertSessionHas('status', 'Saved.');

    $this->friendsTicket->update(['holder_name' => 'Tunde', 'holder_email' => 'tunde@example.com']);
    nameFromReceipt($this->order, $this->friendsTicket, ['name' => 'Tunde Okafor', 'email' => 'tunde@example.com']);

    expect($this->friendsTicket->fresh()->holder_name)->toBe('Tunde Okafor');
    Mail::assertNothingQueued();
});

test('naming a ticket needs a name and a real email, and the errors stay with that ticket', function () {
    nameFromReceipt($this->order, $this->friendsTicket, ['name' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors([
            'name' => 'Enter the name of the person this ticket is for.',
            'email' => 'Enter an email address like name@example.com.',
        ], errorBag: 'ticket'.$this->friendsTicket->id);

    expect($this->friendsTicket->fresh()->holder_name)->toBeNull();
});

test('a ticket can’t be renamed once its headset has been collected', function () {
    $this->friendsTicket->update(['holder_name' => 'Tunde Okafor', 'holder_email' => 'tunde@example.com', 'checked_in_at' => now()]);

    nameFromReceipt($this->order, $this->friendsTicket, ['name' => 'Someone Else', 'email' => 'else@example.com'])
        ->assertSessionHasErrors('ticket', errorBag: 'ticket'.$this->friendsTicket->id);

    expect($this->friendsTicket->fresh()->holder_name)->toBe('Tunde Okafor');
    Mail::assertNothingQueued();
});

test('a receipt can only name its own order’s tickets', function () {
    $otherTicket = Ticket::factory()->create();

    nameFromReceipt($this->order, $otherTicket, ['name' => 'Someone Else', 'email' => 'else@example.com'])->assertNotFound();

    expect($otherTicket->fresh()->holder_name)->toBeNull();
});

test('a refunded order’s tickets can’t be named', function () {
    $this->order->update(['status' => 'refunded']);

    $this->put(route('receipts.tickets.update', [$this->order->receipt_token, $this->friendsTicket]), ['name' => 'Tunde Okafor', 'email' => 'tunde@example.com'])
        ->assertSessionHasErrors('ticket', errorBag: 'ticket'.$this->friendsTicket->id);

    expect($this->friendsTicket->fresh()->holder_name)->toBeNull();
});

test('a friend fills in their blank ticket through its link and is emailed it', function () {
    $this->get($this->friendsTicket->claimUrl())
        ->assertOk()
        ->assertSeeText('Ada Bello got you a ticket')
        ->assertSeeText($this->friendsTicket->code);

    $this->post(route('tickets.claim', $this->friendsTicket->claim_token), ['name' => 'Tunde Okafor', 'email' => 'tunde@example.com'])
        ->assertRedirect($this->friendsTicket->claimUrl());

    expect($this->friendsTicket->fresh())
        ->holder_name->toBe('Tunde Okafor')
        ->holder_email->toBe('tunde@example.com');
    Mail::assertQueued(TicketMail::class, fn (TicketMail $mail): bool => $mail->hasTo('tunde@example.com'));

    $this->get($this->friendsTicket->claimUrl())
        ->assertSeeText('You’re in, Tunde.')
        ->assertDontSee('This ticket is mine');
});

test('a ticket’s link can’t take over a ticket that already has a name', function () {
    $this->post(route('tickets.claim', $this->buyersTicket->claim_token), ['name' => 'Someone Else', 'email' => 'else@example.com'])
        ->assertSessionHasErrors('ticket', errorBag: 'ticket'.$this->buyersTicket->id);

    expect($this->buyersTicket->fresh()->holder_email)->toBe('ada@example.com');
    Mail::assertNothingQueued();
});

test('a ticket’s page can’t be opened with its code, a made-up link, or for an order that isn’t paid', function () {
    $this->get('/tickets/'.$this->friendsTicket->code)->assertNotFound();
    $this->get('/tickets/made-up-token')->assertNotFound();

    $this->order->update(['status' => 'refunded']);
    $this->get($this->friendsTicket->claimUrl())->assertNotFound();
});

test('names the buyer and friends type are escaped on the page and in the email', function () {
    $this->friendsTicket->update(['holder_name' => '<script>alert(1)</script>', 'holder_email' => 'x@example.com']);

    $this->get($this->order->receiptUrl())
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);

    expect((new TicketMail($this->friendsTicket->fresh()))->render())
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});

test('the one-person ticket email gives the code, who bought it and a link to the ticket', function () {
    $this->friendsTicket->update(['holder_name' => 'Tunde Okafor', 'holder_email' => 'tunde@example.com']);

    (new TicketMail($this->friendsTicket->fresh()))
        ->assertSeeInHtml('You’re in, Tunde.')
        ->assertSeeInHtml('Ada Bello got you a ticket')
        ->assertSeeInHtml($this->friendsTicket->code)
        ->assertSeeInHtml(e($this->friendsTicket->claimUrl()), false);
});

test('the tickets email shows who each ticket is for and asks the buyer to name the rest', function () {
    (new TicketsMail($this->order->load(['ticketType', 'tickets'])))
        ->assertSeeInText("Ticket 1: {$this->buyersTicket->code} (Ada Bello)")
        ->assertSeeInText("Ticket 2: {$this->friendsTicket->code} (no name yet)")
        ->assertSeeInText('each ticket needs a name and email');

    $this->friendsTicket->update(['holder_name' => 'Tunde Okafor', 'holder_email' => 'tunde@example.com']);

    (new TicketsMail($this->order->fresh()->load(['ticketType', 'tickets'])))
        ->assertDontSeeInText('each ticket needs a name and email');
});
