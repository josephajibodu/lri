<?php

namespace App\EmailTools\Providers;

use App\EmailTools\Contracts\EmailTool;
use App\EmailTools\Exceptions\EmailToolException;
use App\Enums\EmailToolEvent;
use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Mailchimp's Marketing API (mailchimp.com/developer). Each buyer is a contact in one audience, with the tag for
 * where they stand: adding a tag is what starts a Mailchimp automation flow, and a flow can stop on another tag.
 * Only name, email and order details are sent. Mailchimp's phone field can refuse Nigerian numbers, and follow-up emails don't need them.
 */
class MailchimpProvider implements EmailTool
{
    private const DefaultHost = 'api.mailchimp.com';

    /**
     * The audience fields we fill in. They are plain text, because Mailchimp's website type can't be used as a link's address.
     *
     * @var array<string, string>
     */
    private const MergeFields = [
        'FNAME' => 'First name',
        'LNAME' => 'Last name',
        'ORDERREF' => 'Order reference',
        'RESUMEURL' => 'Resume checkout link',
    ];

    /**
     * @param  array{api_key: ?string, audience_id: ?string, connect_timeout: int, timeout: int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'mailchimp';
    }

    public function prepare(): array
    {
        $existing = collect($this->request(fn (PendingRequest $http) => $http->get($this->audiencePath().'/merge-fields', [
            'count' => 1000,
            'fields' => 'merge_fields.tag',
        ]))->json('merge_fields'))->pluck('tag');

        return collect(self::MergeFields)
            ->reject(fn (string $name, string $tag): bool => $existing->contains($tag))
            ->map(function (string $name, string $tag): string {
                $this->request(fn (PendingRequest $http) => $http->post($this->audiencePath().'/merge-fields', [
                    'tag' => $tag,
                    'name' => $name,
                    'type' => 'text',
                ]));

                return "Added the audience field {$tag}";
            })
            ->values()
            ->all();
    }

    /**
     * Add or update the contact, then give them the tag for this news and take off the others. Someone already in the
     * audience keeps their subscription status, so a buyer who unsubscribed is never signed back up. Paying clears the
     * resume link, because that link leads to the receipt and its ticket codes once the order is paid.
     */
    public function send(EmailToolEvent $event, Order $order): void
    {
        $memberPath = $this->audiencePath().'/members/'.md5(Str::lower($order->buyer_email));

        $this->request(fn (PendingRequest $http) => $http->put($memberPath, [
            'email_address' => $order->buyer_email,
            'status_if_new' => 'subscribed',
            'merge_fields' => array_filter([
                'FNAME' => Str::before($order->buyer_name, ' '),
                'LNAME' => Str::contains($order->buyer_name, ' ') ? Str::after($order->buyer_name, ' ') : null,
                'ORDERREF' => $order->reference,
            ]) + ['RESUMEURL' => $event === EmailToolEvent::OrderPaid ? '' : $order->statusUrl()],
        ]));

        $this->request(fn (PendingRequest $http) => $http->post($memberPath.'/tags', [
            'tags' => array_map(fn (EmailToolEvent $case): array => [
                'name' => $this->tagFor($case),
                'status' => $case === $event ? 'active' : 'inactive',
            ], EmailToolEvent::cases()),
        ]));
    }

    /**
     * The tag a contact carries for the news. Automation flows in Mailchimp are set up against these names.
     */
    private function tagFor(EmailToolEvent $event): string
    {
        return match ($event) {
            EmailToolEvent::CheckoutStarted => 'checkout-started',
            EmailToolEvent::CheckoutAbandoned => 'checkout-abandoned',
            EmailToolEvent::OrderPaid => 'ticket-paid',
        };
    }

    private function audiencePath(): string
    {
        $audienceId = $this->config['audience_id'] ?? null;

        if (blank($audienceId)) {
            throw new EmailToolException('Mailchimp is not set up: MAILCHIMP_AUDIENCE_ID is empty.', 'mailchimp');
        }

        return '/lists/'.rawurlencode($audienceId);
    }

    /**
     * Send a request to Mailchimp, and turn any failure into an EmailToolException. Retries are left to the queue.
     *
     * @param  callable(PendingRequest): Response  $request
     */
    private function request(callable $request): Response
    {
        try {
            $response = $request($this->http());
        } catch (ConnectionException $exception) {
            throw new EmailToolException('Could not reach Mailchimp: '.$exception->getMessage(), 'mailchimp', isRetryable: true, previous: $exception);
        }

        if ($response->failed()) {
            throw EmailToolException::fromResponse('Mailchimp', $response);
        }

        return $response;
    }

    private function http(): PendingRequest
    {
        $apiKey = $this->config['api_key'] ?? null;

        if (blank($apiKey)) {
            throw new EmailToolException('Mailchimp is not set up: MAILCHIMP_API_KEY is empty.', 'mailchimp');
        }

        return Http::baseUrl('https://'.$this->hostFor($apiKey).'/3.0')
            ->withBasicAuth('lri', $apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout($this->config['connect_timeout'])
            ->timeout($this->config['timeout']);
    }

    /**
     * Mailchimp API keys can end in the account's data center, such as -us21, which is then part of the API address.
     */
    private function hostFor(string $apiKey): string
    {
        return preg_match('/-([a-z]+\d+)$/', $apiKey, $matches) ? "{$matches[1]}.".self::DefaultHost : self::DefaultHost;
    }
}
