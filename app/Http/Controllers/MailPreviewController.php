<?php

namespace App\Http\Controllers;

use App\Enums\CouponDiscountType;
use App\Enums\OrderStatus;
use App\Enums\StaffRole;
use App\Mail\AdminInvitationMail;
use App\Mail\TicketMail;
use App\Mail\TicketsMail;
use App\Models\AdminInvitation;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Every email the site sends, filled with sample data so it can be checked in the browser. Nothing is saved or
 * sent. Never routed in production.
 */
class MailPreviewController extends Controller
{
    /**
     * The screen sizes an email can be previewed at, in pixels. Desktop has none because it fills the page.
     *
     * @var array<string, array{name: string, width: int|null, height: int|null}>
     */
    private const DEVICES = [
        'desktop' => ['name' => 'Desktop', 'width' => null, 'height' => null],
        'tablet' => ['name' => 'Tablet', 'width' => 768, 'height' => 1024],
        'mobile' => ['name' => 'Mobile', 'width' => 390, 'height' => 844],
    ];

    /**
     * List the emails, with the chosen one shown at the chosen screen size.
     */
    public function index(Request $request, ?string $preview = null): View
    {
        $previews = $this->previews();
        $selected = $preview ?? $previews->keys()->first();

        abort_unless($previews->has($selected), 404);

        return view('mail-previews.index', [
            'devices' => self::DEVICES,
            'device' => in_array($request->query('device'), array_keys(self::DEVICES), true) ? $request->query('device') : 'desktop',
            'previews' => $previews->map(fn (array $preview): array => [
                ...$preview,
                'subject' => $preview['mail']()->envelope()->subject,
            ]),
            'selected' => $selected,
        ]);
    }

    /**
     * Render one email on its own, exactly as the buyer's mail app would get it.
     */
    public function show(string $preview): Mailable
    {
        $mail = $this->previews()->get($preview)['mail'] ?? abort(404);

        return $mail();
    }

    /**
     * The page a link in the previewed invitation email opens, filled with the same sample data. Its buttons are
     * switched off, because there's no real invitation to answer.
     */
    public function invitationPage(string $page): View
    {
        return view("admin-invitations.{$page}", [
            'invitation' => $this->sampleInvitation(),
            'acceptUrl' => route('mail-previews.invitation-page', 'accept'),
            'declineUrl' => route('mail-previews.invitation-page', 'decline'),
            'isPreview' => true,
        ]);
    }

    /**
     * The emails to preview, keyed by the name used in the link.
     *
     * @return Collection<string, array{name: string, description: string, mail: callable(): Mailable}>
     */
    private function previews(): Collection
    {
        return collect([
            'tickets' => [
                'name' => 'Tickets, one ticket',
                'description' => 'Sent to the buyer once their order is paid.',
                'mail' => fn (): Mailable => new TicketsMail($this->sampleOrder(quantity: 1)),
            ],
            'tickets-several' => [
                'name' => 'Tickets, several tickets',
                'description' => 'The same email when the buyer paid for more than one ticket.',
                'mail' => fn (): Mailable => new TicketsMail($this->sampleOrder(quantity: 3)),
            ],
            'tickets-coupon' => [
                'name' => 'Tickets, with a coupon',
                'description' => 'The same email when a coupon took money off the order.',
                'mail' => fn (): Mailable => new TicketsMail($this->sampleOrder(quantity: 2, withCoupon: true)),
            ],
            'ticket' => [
                'name' => 'One person’s ticket',
                'description' => 'Sent to a friend the buyer got a ticket for, once their name goes on it.',
                'mail' => fn (): Mailable => new TicketMail($this->sampleOrder(quantity: 3)->tickets[1]),
            ],
            'admin-invitation' => [
                'name' => 'Invitation to the admin',
                'description' => 'Sent when an organiser invites someone to help run the admin. Our official look, for anything that isn’t about an event.',
                'mail' => fn (): Mailable => new AdminInvitationMail(
                    $this->sampleInvitation(),
                    acceptUrl: route('mail-previews.invitation-page', 'accept'),
                    declineUrl: route('mail-previews.invitation-page', 'decline'),
                ),
            ],
        ]);
    }

    /**
     * An invitation with its roles and who sent it, built in memory so nothing touches the database.
     */
    private function sampleInvitation(): AdminInvitation
    {
        return (new AdminInvitation(['email' => 'kemi@example.com']))
            ->forceFill(['expires_at' => now()->addHours(config('lri.admin_invitations.lifetime_hours'))])
            ->setRelation('invitedBy', new User(['name' => 'Joseph Ajibodu']))
            ->setRelation('roles', collect([new Role(['name' => StaffRole::HeadsetDesk->value])]));
    }

    /**
     * A paid Disco Run order with its tickets, built in memory so nothing touches the database.
     */
    private function sampleOrder(int $quantity, bool $withCoupon = false): Order
    {
        $ticketType = new TicketType([
            'event' => 'disco-run',
            'name' => 'Standard ticket',
            'price' => 1_000_000,
            'currency' => 'NGN',
        ]);

        $order = new Order([
            'reference' => 'LRI-7K3Q9P',
            'receipt_token' => 'sampleReceiptToken0000000000000',
            'quantity' => $quantity,
            'unit_price' => $ticketType->price,
            'amount' => $ticketType->price * $quantity,
            'currency' => $ticketType->currency,
            'status' => OrderStatus::Paid,
            'buyer_name' => 'Ada Bello',
            'buyer_email' => 'ada@example.com',
            'paid_at' => now(),
        ]);

        $order->setRelation('ticketType', $ticketType);

        if ($withCoupon) {
            $coupon = new Coupon(['code' => 'LRILOVE', 'discount_type' => CouponDiscountType::Percentage, 'discount_value' => 20]);
            $discount = $coupon->discountFor($order->unitPrice(), $quantity)->amount;

            $order->forceFill(['discount_amount' => $discount, 'amount' => $order->amount - $discount])->setRelation('coupon', $coupon);
        }

        $tickets = collect([
            ['7K3Q-9PXM', 'Ada Bello', 'ada@example.com'],
            ['H4TW-2NRC', 'Tunde Okafor', 'tunde@example.com'],
            ['P8LD-6VKE', null, null],
        ])
            ->take($quantity)
            ->map(function (array $ticket) use ($order, $ticketType): Ticket {
                [$code, $name, $email] = $ticket;

                return (new Ticket(['code' => $code, 'holder_name' => $name, 'holder_email' => $email]))
                    ->forceFill(['claim_token' => 'sampleTicketToken'.$code])
                    ->setRelation('order', $order)
                    ->setRelation('ticketType', $ticketType);
            });

        return $order->setRelation('tickets', $tickets);
    }
}
