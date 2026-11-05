<?php

use App\Mail\TicketMail;
use App\Mail\TicketsMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Support\TicketQrCode;
use chillerlan\QRCode\QRCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
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

test('the receipt shows the buyer’s own ticket with its QR code, and offers to name or send each blank one', function () {
    $this->get($this->order->receiptUrl())
        ->assertOk()
        ->assertSeeText('Yours')
        ->assertSeeText('Ada Bello · ada@example.com')
        ->assertSee('QR code for ticket '.$this->buyersTicket->code, false)
        ->assertSeeText('No name yet')
        ->assertSeeText('Type details')
        ->assertSeeText('Send link')
        ->assertSee($this->friendsTicket->claimUrl(), false)
        ->assertDontSee($this->buyersTicket->claimUrl(), false)
        ->assertDontSee('QR code for ticket '.$this->friendsTicket->code, false);
});

test('a named friend’s ticket shows as emailed, with Edit and Reissue', function () {
    $this->friendsTicket->update(['holder_name' => 'Tunde Okafor', 'holder_email' => 'tunde@example.com']);

    $this->get($this->order->receiptUrl())
        ->assertSeeText('Emailed')
        ->assertSeeText('Tunde Okafor · tunde@example.com')
        ->assertSeeText('Edit')
        ->assertSeeText('Reissue')
        ->assertDontSee($this->friendsTicket->claimUrl(), false);
});

test('sending a blank ticket’s link marks it as sent, with Copy link and Cancel link', function () {
    $this->post(route('receipts.tickets.share-link', [$this->order->receipt_token, $this->friendsTicket]))
        ->assertRedirect($this->order->receiptUrl().'#ticket-'.$this->friendsTicket->id);

    expect($this->friendsTicket->fresh()->link_shared_at)->not->toBeNull();

    $this->get($this->order->receiptUrl())
        ->assertSeeText('Link sent')
        ->assertSeeText('Copy link')
        ->assertSeeText('Cancel link')
        ->assertSee($this->friendsTicket->claimUrl(), false);
});

test('cancelling a sent link stops it working and leaves a fresh link to send', function () {
    $this->friendsTicket->update(['link_shared_at' => now()]);
    $oldLink = $this->friendsTicket->claimUrl();

    $this->delete(route('receipts.tickets.cancel-link', [$this->order->receipt_token, $this->friendsTicket]))->assertRedirect();

    $ticket = $this->friendsTicket->fresh();
    expect($ticket)->link_shared_at->toBeNull()->code->toBe($this->friendsTicket->code)
        ->and($ticket->claimUrl())->not->toBe($oldLink);

    $this->get($oldLink)->assertNotFound();
    $this->get($ticket->claimUrl())->assertOk();
});

test('reissuing gives a new code and link, so the old code, QR and link stop working', function () {
    $this->friendsTicket->update(['holder_name' => 'Tunde Okafor', 'holder_email' => 'tunde@example.com']);
    $old = $this->friendsTicket->fresh();

    $this->post(route('receipts.tickets.reissue', [$this->order->receipt_token, $this->friendsTicket]))->assertRedirect();

    $new = $this->friendsTicket->fresh();
    expect($new)
        ->code->not->toBe($old->code)
        ->claim_token->not->toBe($old->claim_token)
        ->holder_name->toBeNull()
        ->holder_email->toBeNull()
        ->and(TicketQrCode::svg($new))->not->toBe(TicketQrCode::svg($old))
        ->and(Ticket::query()->where('code', $old->code)->exists())->toBeFalse();

    $this->get($old->claimUrl())->assertNotFound();
    $this->get($new->claimUrl())->assertOk()->assertSeeText('This ticket is mine');
});

test('a ticket’s link and code can’t be changed once its headset has been collected', function (string $route, string $method) {
    $this->friendsTicket->update(['holder_name' => 'Tunde Okafor', 'holder_email' => 'tunde@example.com', 'checked_in_at' => now()]);
    $before = $this->friendsTicket->fresh();

    $this->call($method, route($route, [$this->order->receipt_token, $this->friendsTicket]))
        ->assertSessionHasErrors('ticket', errorBag: 'ticket'.$this->friendsTicket->id);

    expect($this->friendsTicket->fresh())->code->toBe($before->code)->claim_token->toBe($before->claim_token);
})->with([
    'reissue' => ['receipts.tickets.reissue', 'POST'],
    'cancel link' => ['receipts.tickets.cancel-link', 'DELETE'],
]);

test('only blank tickets have a link to send or cancel', function (string $route, string $method) {
    $this->call($method, route($route, [$this->order->receipt_token, $this->buyersTicket]))
        ->assertSessionHasErrors('ticket', errorBag: 'ticket'.$this->buyersTicket->id);

    expect($this->buyersTicket->fresh()->claim_token)->toBe($this->buyersTicket->claim_token);
})->with([
    'send link' => ['receipts.tickets.share-link', 'POST'],
    'cancel link' => ['receipts.tickets.cancel-link', 'DELETE'],
]);

test('a receipt can’t reissue or cancel another order’s ticket', function () {
    $otherTicket = Ticket::factory()->named()->create();

    $this->post(route('receipts.tickets.reissue', [$this->order->receipt_token, $otherTicket]))->assertNotFound();

    expect($otherTicket->fresh()->code)->toBe($otherTicket->code);
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

test('the buyer is not emailed again for their own ticket', function () {
    nameFromReceipt($this->order, $this->buyersTicket, ['name' => 'Ada B.', 'email' => 'ADA@example.com'])->assertSessionHas('status', 'Saved.');

    Mail::assertNothingQueued();
});

test('editing a friend’s ticket fixes the typo and emails it again with the same code', function () {
    $this->friendsTicket->update(['holder_name' => 'Tunde Okafr', 'holder_email' => 'tunde@example.com']);

    nameFromReceipt($this->order, $this->friendsTicket, ['name' => 'Tunde Okafor', 'email' => 'tunde@example.com']);

    expect($this->friendsTicket->fresh())->holder_name->toBe('Tunde Okafor')->code->toBe($this->friendsTicket->code);
    Mail::assertQueued(TicketMail::class, fn (TicketMail $mail): bool => $mail->hasTo('tunde@example.com'));
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
        ->assertSeeText('Someone got you a ticket')
        ->assertSeeText($this->friendsTicket->code)
        ->assertDontSeeText('Ada Bello')
        ->assertDontSeeText('ada@example.com')
        ->assertDontSeeText('₦')
        ->assertDontSeeText($this->buyersTicket->code)
        ->assertDontSee('QR code for ticket', false);

    $this->post(route('tickets.claim', $this->friendsTicket->claim_token), ['name' => 'Tunde Okafor', 'email' => 'tunde@example.com'])
        ->assertRedirect($this->friendsTicket->claimUrl());

    expect($this->friendsTicket->fresh())
        ->holder_name->toBe('Tunde Okafor')
        ->holder_email->toBe('tunde@example.com');
    Mail::assertQueued(TicketMail::class, fn (TicketMail $mail): bool => $mail->hasTo('tunde@example.com'));

    $this->get($this->friendsTicket->claimUrl())
        ->assertSeeText('You’re in, Tunde.')
        ->assertSee('QR code for ticket '.$this->friendsTicket->code, false)
        ->assertDontSee('This ticket is mine')
        ->assertDontSeeText('Reissue')
        ->assertDontSeeText('Cancel link');
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
        ->assertSeeInHtml('alt="QR code for ticket '.$this->friendsTicket->code.'"', false)
        ->assertSeeInText('Name: Tunde Okafor')
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

test('a ticket’s QR code opens the headset desk on that ticket', function () {
    expect($this->friendsTicket->deskUrl())->toBe(url('/admin/headset-desk').'?search='.urlencode($this->friendsTicket->code));
});

test('the buyer’s tickets email carries the QR code of their own ticket', function () {
    (new TicketsMail($this->order->load(['ticketType', 'tickets'])))
        ->assertSeeInHtml('alt="QR code for ticket '.$this->buyersTicket->code.'"', false)
        ->assertSeeInHtml('Admit one')
        ->assertDontSeeInHtml('alt="QR code for ticket '.$this->friendsTicket->code.'"', false);
});

test('a sent ticket email attaches a QR code image that scans back to the desk link', function () {
    $this->friendsTicket->update(['holder_name' => 'Tunde Okafor', 'holder_email' => 'tunde@example.com']);

    $mailer = (new MailManager(app()))->mailer('array');
    $mailer->to('tunde@example.com')->send(new TicketMail($this->friendsTicket->fresh()));

    $email = $mailer->getSymfonyTransport()->messages()->last()->getOriginalMessage();
    $qr = collect($email->getAttachments())->first(fn ($part): bool => $part->getFilename() === 'ticket-'.$this->friendsTicket->code.'.png');

    expect($qr)->not->toBeNull()
        ->and((new QRCode)->readFromBlob($qr->getBody())->data)->toBe($this->friendsTicket->deskUrl())
        ->and($email->getHtmlBody())->toContain('cid:')
        ->not->toContain('data:image/png');
});
