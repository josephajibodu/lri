<?php

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

const MailchimpMergeFieldsUrl = 'us21.api.mailchimp.com/3.0/lists/aud_1/merge-fields*';

/**
 * Have Mailchimp's audience already hold the given fields, and accept new ones.
 *
 * @param  list<string>  $tags
 */
function fakeMailchimpMergeFields(array $tags): void
{
    Http::fake([MailchimpMergeFieldsUrl => fn (ClientRequest $request) => $request->method() === 'GET'
        ? Http::response(['merge_fields' => array_map(fn (string $tag): array => ['tag' => $tag], $tags)])
        : Http::response(['tag' => $request['tag']])]);
}

test('setting up adds the missing audience fields as plain text and leaves the rest alone', function () {
    connectMailchimp();
    fakeMailchimpMergeFields(['FNAME', 'LNAME']);

    $this->artisan('email-tools:prepare')
        ->expectsOutputToContain('Added the audience field ORDERREF')
        ->expectsOutputToContain('Added the audience field RESUMEURL')
        ->assertSuccessful();

    Http::assertSentCount(3);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST' && $request['tag'] === 'RESUMEURL' && $request['type'] === 'text');
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'POST' && $request['tag'] === 'FNAME');
});

test('setting up again changes nothing', function () {
    connectMailchimp();
    fakeMailchimpMergeFields(['FNAME', 'LNAME', 'ORDERREF', 'RESUMEURL']);

    $this->artisan('email-tools:prepare')
        ->expectsOutputToContain('Everything was already set up.')
        ->assertSuccessful();

    Http::assertSentCount(1);
});

test('setting up with no email tool connected says so and fails', function () {
    Http::preventStrayRequests();

    $this->artisan('email-tools:prepare')
        ->expectsOutputToContain('No email tool is connected')
        ->assertFailed();
});

test('setting up with a key Mailchimp refuses says why and fails', function () {
    connectMailchimp();
    Http::fake([MailchimpMergeFieldsUrl => Http::response(['detail' => 'Your API key may be invalid.'], 401)]);

    $this->artisan('email-tools:prepare')
        ->expectsOutputToContain('Mailchimp returned 401: Your API key may be invalid.')
        ->assertFailed();
});
