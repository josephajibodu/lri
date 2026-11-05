<?php

use App\EmailTools\EmailToolManager;
use App\EmailTools\Exceptions\EmailToolException;
use App\Enums\EmailToolEvent;
use App\Enums\EmailToolSendStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Jobs\SendToEmailTool;
use App\Models\EmailToolSend;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Have Mailchimp accept contacts and tags, answering that the contact has the given status.
 */
function fakeMailchimp(string $memberStatus = 'subscribed'): void
{
    connectMailchimp();

    Http::fake([
        'us21.api.mailchimp.com/3.0/lists/aud_1/members/*/tags' => Http::response(null, 204),
        'us21.api.mailchimp.com/3.0/lists/aud_1/members/*' => Http::response(['id' => 'member_1', 'status' => $memberStatus]),
    ]);
}

/**
 * Whether Mailchimp was asked to change an existing contact's status.
 */
function mailchimpStatusWasChanged(): bool
{
    return Http::recorded(fn (ClientRequest $request): bool => $request->method() === 'PATCH')->isNotEmpty();
}

/**
 * The one request sent to Mailchimp with the given method.
 */
function mailchimpRequest(string $method): ClientRequest
{
    return Http::recorded(fn (ClientRequest $request): bool => $request->method() === $method)->sole()[0];
}

/**
 * The tags sent in the given state.
 *
 * @return list<string>
 */
function mailchimpTags(string $status): array
{
    return collect(mailchimpRequest('POST')['tags'])->where('status', $status)->pluck('name')->values()->all();
}

/**
 * Submit the ticket form, with or without ticking the consent box.
 */
function placeOrderForEmailTool(bool $consent): Order
{
    fakeBachs();

    $ticketType = TicketType::factory()->create();
    $channel = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->create();

    test()->post(route('orders.store'), array_filter([
        'ticket_type' => $ticketType->slug,
        'quantity' => 1,
        'name' => 'Ada Bello',
        'email' => 'ada@example.com',
        'channel' => $channel->id,
        'marketing_consent' => $consent ? '1' : null,
    ]));

    return Order::query()->sole();
}

test('placing an order with the consent box ticked queues the news for the email tool', function () {
    Queue::fake([SendToEmailTool::class]);
    connectMailchimp();

    $order = placeOrderForEmailTool(consent: true);

    $send = EmailToolSend::query()->sole();

    expect($send->order_id)->toBe($order->id)
        ->and($send->tool)->toBe('mailchimp')
        ->and($send->event)->toBe(EmailToolEvent::CheckoutStarted)
        ->and($send->status)->toBe(EmailToolSendStatus::Waiting);

    Queue::assertPushed(SendToEmailTool::class, fn (SendToEmailTool $job): bool => $job->send->is($send));
});

test('an abandoned order queues the news for the email tool', function () {
    Queue::fake([SendToEmailTool::class]);
    connectMailchimp();
    Order::factory()->create(['marketing_consent' => true, 'expires_at' => now()->subMinutes(15)]);

    $this->artisan('orders:abandon-unpaid')->assertSuccessful();

    expect(EmailToolSend::query()->sole()->event)->toBe(EmailToolEvent::CheckoutAbandoned);
    Queue::assertPushedTimes(SendToEmailTool::class, 1);
});

test('a paid order queues the news for the email tool', function () {
    Mail::fake();
    Queue::fake([SendToEmailTool::class]);
    fakeBachs();
    connectMailchimp();
    $order = Order::factory()->create(['marketing_consent' => true, 'quantity' => 1, 'amount' => 1_000_000]);
    $payment = Payment::factory()->for($order)->create(['amount' => 1_000_000]);

    deliverBachsWebhook(bachsEvent('collection.succeeded', ['checkout_id' => $payment->provider_checkout_id, 'amount' => '10000.00', 'status' => 'SUCCEEDED']))->assertOk();

    expect(EmailToolSend::query()->sole()->event)->toBe(EmailToolEvent::OrderPaid);
    Queue::assertPushedTimes(SendToEmailTool::class, 1);
});

test('a buyer who didn’t tick the box never reaches the email tool, whatever happens to their order', function () {
    Mail::fake();
    Queue::fake([SendToEmailTool::class]);
    connectMailchimp();

    $placed = placeOrderForEmailTool(consent: false);
    $placed->update(['expires_at' => now()->subMinutes(15)]);
    $placed->latestPayment->update(['expires_at' => now()->subMinutes(15)]);
    Http::fake(['sandbox-api.bachs.io/v1/checkout-sessions/*' => Http::response(bachsCheckoutSession($placed->latestPayment, paid: false))]);
    $this->artisan('orders:abandon-unpaid')->assertSuccessful();

    $paid = Order::factory()->create(['marketing_consent' => false, 'quantity' => 1, 'amount' => 1_000_000]);
    $payment = Payment::factory()->for($paid)->create(['amount' => 1_000_000]);
    deliverBachsWebhook(bachsEvent('collection.succeeded', ['checkout_id' => $payment->provider_checkout_id, 'amount' => '10000.00', 'status' => 'SUCCEEDED']))->assertOk();

    expect($placed->fresh()->status)->toBe(OrderStatus::Abandoned)
        ->and($paid->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(EmailToolSend::query()->count())->toBe(0);
    Queue::assertNotPushed(SendToEmailTool::class);
});

test('with no email tool connected, nothing is queued', function () {
    Queue::fake([SendToEmailTool::class]);

    placeOrderForEmailTool(consent: true);

    expect(EmailToolSend::query()->count())->toBe(0);
    Queue::assertNotPushed(SendToEmailTool::class);
});

test('an order that lapses twice only starts one round of follow-ups', function () {
    Queue::fake([SendToEmailTool::class]);
    connectMailchimp();
    $order = Order::factory()->create(['marketing_consent' => true, 'expires_at' => now()->subMinutes(15)]);

    $this->artisan('orders:abandon-unpaid')->assertSuccessful();
    $order->update(['status' => OrderStatus::Pending, 'abandoned_at' => null]);
    $this->artisan('orders:abandon-unpaid')->assertSuccessful();

    expect(EmailToolSend::query()->count())->toBe(1);
    Queue::assertPushedTimes(SendToEmailTool::class, 1);
});

test('a started checkout adds the buyer as a contact, with only their name and order, and tags them', function () {
    fakeMailchimp();
    $order = Order::factory()->create(['buyer_email' => 'Ada@Example.com', 'buyer_name' => 'Ada Bello Okafor', 'buyer_phone' => '+2348031234567']);
    $send = EmailToolSend::factory()->for($order)->create();

    SendToEmailTool::dispatch($send);

    $member = mailchimpRequest('PUT');

    expect($member->url())->toBe('https://us21.api.mailchimp.com/3.0/lists/aud_1/members/3e3417d7ef77d5932a6734b916515ed5')
        ->and($member->hasHeader('Authorization', 'Basic '.base64_encode('lri:testkey-us21')))->toBeTrue()
        ->and($member['email_address'])->toBe('Ada@Example.com')
        ->and($member['merge_fields'])->toMatchArray(['FNAME' => 'Ada', 'LNAME' => 'Bello Okafor', 'ORDERREF' => $order->reference])
        ->and($member->body())->not->toContain('2348031234567')
        ->and(mailchimpRequest('POST')->url())->toBe('https://us21.api.mailchimp.com/3.0/lists/aud_1/members/3e3417d7ef77d5932a6734b916515ed5/tags')
        ->and(mailchimpTags('active'))->toBe(['checkout-started'])
        ->and(mailchimpTags('inactive'))->toBe(['checkout-abandoned', 'ticket-paid'])
        ->and($send->fresh())
        ->status->toBe(EmailToolSendStatus::Sent)
        ->attempts->toBe(1)
        ->sent_at->not->toBeNull();
});

test('an email typed on the ticket form with the box ticked isn’t subscribed before payment', function () {
    fakeMailchimp('pending');

    placeOrderForEmailTool(consent: true);

    expect(EmailToolSend::query()->sole()->status)->toBe(EmailToolSendStatus::Sent)
        ->and(mailchimpRequest('PUT')->data())
        ->toHaveKey('status_if_new', 'pending')
        ->not->toHaveKey('status')
        ->and(mailchimpStatusWasChanged())->toBeFalse();
});

test('a new contact from an abandoned order is asked to confirm too', function () {
    fakeMailchimp('pending');
    $order = Order::factory()->abandoned()->create();

    SendToEmailTool::dispatch(EmailToolSend::factory()->for($order)->create(['event' => EmailToolEvent::CheckoutAbandoned]));

    expect(mailchimpRequest('PUT')->data())
        ->toHaveKey('status_if_new', 'pending')
        ->not->toHaveKey('status')
        ->and(mailchimpStatusWasChanged())->toBeFalse();
});

test('a new contact who pays is subscribed straight away', function () {
    fakeMailchimp();
    $order = Order::factory()->paid()->create();

    SendToEmailTool::dispatch(EmailToolSend::factory()->for($order)->create(['event' => EmailToolEvent::OrderPaid]));

    expect(mailchimpRequest('PUT')->data())
        ->toHaveKey('status_if_new', 'subscribed')
        ->not->toHaveKey('status')
        ->and(mailchimpStatusWasChanged())->toBeFalse();
});

test('a contact still waiting to confirm is subscribed once they pay, before their tags change', function () {
    fakeMailchimp('pending');
    $order = Order::factory()->paid()->create(['buyer_email' => 'Ada@Example.com']);

    SendToEmailTool::dispatch(EmailToolSend::factory()->for($order)->create(['event' => EmailToolEvent::OrderPaid]));

    $statusChange = mailchimpRequest('PATCH');

    expect($statusChange->url())->toBe('https://us21.api.mailchimp.com/3.0/lists/aud_1/members/3e3417d7ef77d5932a6734b916515ed5')
        ->and($statusChange->data())->toBe(['status' => 'subscribed'])
        ->and(Http::recorded()->map(fn (array $call): string => $call[0]->method())->all())->toBe(['PUT', 'PATCH', 'POST']);
});

test('someone who unsubscribed or bounced is never signed back up, even if they pay', function (string $memberStatus) {
    fakeMailchimp($memberStatus);
    $order = Order::factory()->paid()->create();

    SendToEmailTool::dispatch(EmailToolSend::factory()->for($order)->create(['event' => EmailToolEvent::OrderPaid]));

    expect(mailchimpRequest('PUT')->data())->not->toHaveKey('status')
        ->and(mailchimpStatusWasChanged())->toBeFalse();
})->with(['unsubscribed', 'cleaned']);

test('an abandoned checkout sends a working link back to the order, short enough for Mailchimp to keep', function () {
    fakeMailchimp();
    PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->create();
    $order = Order::factory()->abandoned()->create();

    SendToEmailTool::dispatch(EmailToolSend::factory()->for($order)->create(['event' => EmailToolEvent::CheckoutAbandoned]));

    $resumeUrl = mailchimpRequest('PUT')['merge_fields']['RESUMEURL'];

    expect(strlen($resumeUrl))->toBeLessThanOrEqual(255)
        ->and(mailchimpTags('active'))->toBe(['checkout-abandoned']);

    $this->get($resumeUrl)
        ->assertOk()
        ->assertSeeText('This order expired')
        ->assertSeeText('Pay now');
});

test('a paid order takes off the follow-up tags and clears the link back to the order', function () {
    fakeMailchimp();
    $order = Order::factory()->paid()->create();

    SendToEmailTool::dispatch(EmailToolSend::factory()->for($order)->create(['event' => EmailToolEvent::OrderPaid]));

    expect(mailchimpTags('active'))->toBe(['ticket-paid'])
        ->and(mailchimpTags('inactive'))->toBe(['checkout-started', 'checkout-abandoned'])
        ->and(mailchimpRequest('PUT')['merge_fields'])->toHaveKey('RESUMEURL', '');
});

test('news that is no longer true is skipped, so a buyer who paid gets no follow-ups', function (OrderStatus $orderStatus, EmailToolEvent $event) {
    fakeMailchimp();
    $send = EmailToolSend::factory()->for(Order::factory()->create(['status' => $orderStatus]))->create(['event' => $event]);

    SendToEmailTool::dispatch($send);

    expect($send->fresh())
        ->status->toBe(EmailToolSendStatus::Skipped)
        ->attempts->toBe(0);
    Http::assertNothingSent();
})->with([
    'abandoned, but since paid' => [OrderStatus::Paid, EmailToolEvent::CheckoutAbandoned],
    'started, but since abandoned' => [OrderStatus::Abandoned, EmailToolEvent::CheckoutStarted],
]);

test('news already sent isn’t sent twice', function () {
    fakeMailchimp();
    $send = EmailToolSend::factory()->sent()->create();

    SendToEmailTool::dispatch($send);

    Http::assertNothingSent();
});

test('a brief problem at the email tool is left for the queue to try again', function (Closure $response) {
    connectMailchimp();
    Http::fake(['us21.api.mailchimp.com/*' => $response]);
    $send = EmailToolSend::factory()->create();
    $job = (new SendToEmailTool($send))->withFakeQueueInteractions();

    expect(fn () => $job->handle(app(EmailToolManager::class)))->toThrow(EmailToolException::class);

    $job->assertNotFailed();
    expect($send->fresh())
        ->status->toBe(EmailToolSendStatus::Waiting)
        ->error->not->toBeNull();
})->with([
    'too many requests' => [fn () => fn () => Http::response(['detail' => 'Too many requests'], 429)],
    'an outage' => [fn () => fn () => Http::response(['detail' => 'Down'], 503)],
    'a dropped connection' => [fn () => fn () => Http::failedConnection()],
]);

test('a refusal fails straight away and says why, without retrying', function () {
    connectMailchimp();
    Http::fake(['us21.api.mailchimp.com/*' => Http::response([
        'title' => 'Invalid Resource',
        'status' => 400,
        'detail' => 'Your merge fields were invalid.',
        'errors' => [['field' => 'RESUMEURL', 'message' => 'Please enter a value']],
    ], 400)]);
    $send = EmailToolSend::factory()->create();

    SendToEmailTool::dispatch($send);

    Http::assertSentCount(1);
    expect($send->fresh())
        ->status->toBe(EmailToolSendStatus::Failed)
        ->failed_at->not->toBeNull()
        ->error->toBe('Mailchimp returned 400: Your merge fields were invalid. Please enter a value');
});

test('when the queue gives up after its retries, the failure is noted for organisers', function () {
    $send = EmailToolSend::factory()->create();

    (new SendToEmailTool($send))->failed(new EmailToolException('Mailchimp returned 503: Down', 'mailchimp', isRetryable: true));

    expect($send->fresh())
        ->status->toBe(EmailToolSendStatus::Failed)
        ->failed_at->not->toBeNull()
        ->error->toBe('Mailchimp returned 503: Down');
});

test('a Mailchimp connection missing its details fails and says what’s missing', function (string $setting, string $envName) {
    connectMailchimp();
    Http::fake();
    config()->set("email_tools.tools.mailchimp.{$setting}", null);
    $send = EmailToolSend::factory()->create();

    SendToEmailTool::dispatch($send);

    Http::assertNothingSent();
    expect($send->fresh())
        ->status->toBe(EmailToolSendStatus::Failed)
        ->error->toContain($envName);
})->with([
    'no API key' => ['api_key', 'MAILCHIMP_API_KEY'],
    'no audience' => ['audience_id', 'MAILCHIMP_AUDIENCE_ID'],
]);

test('an API key with no data center uses Mailchimp’s main address', function () {
    connectMailchimp();
    config()->set('email_tools.tools.mailchimp.api_key', 'testkey');
    Http::fake([
        'api.mailchimp.com/3.0/lists/aud_1/members/*/tags' => Http::response(null, 204),
        'api.mailchimp.com/3.0/lists/aud_1/members/*' => Http::response(['id' => 'member_1']),
    ]);
    $send = EmailToolSend::factory()->create();

    SendToEmailTool::dispatch($send);

    expect(mailchimpRequest('PUT')->url())->toStartWith('https://api.mailchimp.com/3.0/')
        ->and($send->fresh()->status)->toBe(EmailToolSendStatus::Sent);
});
