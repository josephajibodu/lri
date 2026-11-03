<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Mail\TicketsMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Every email the site sends, filled with sample data so it can be checked in the browser. Nothing is saved or
 * sent. Never routed in production.
 */
class MailPreviewController extends Controller
{
    /**
     * List the emails, with the chosen one shown beside the list.
     */
    public function index(?string $preview = null): View
    {
        $previews = $this->previews();
        $selected = $preview ?? $previews->keys()->first();

        abort_unless($previews->has($selected), 404);

        return view('mail-previews.index', [
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
        ]);
    }

    /**
     * A paid Disco Run order with its tickets, built in memory so nothing touches the database.
     */
    private function sampleOrder(int $quantity): Order
    {
        $ticketType = new TicketType([
            'event' => 'disco-run',
            'name' => 'Standard ticket',
            'price' => 1_000_000,
            'currency' => 'NGN',
        ]);

        $order = new Order([
            'reference' => 'LRI-7K3Q9P',
            'quantity' => $quantity,
            'unit_price' => $ticketType->price,
            'amount' => $ticketType->price * $quantity,
            'currency' => $ticketType->currency,
            'status' => OrderStatus::Paid,
            'buyer_name' => 'Ada Bello',
            'buyer_email' => 'ada@example.com',
            'paid_at' => now(),
        ]);

        $tickets = collect(['7K3Q-9PXM', 'H4TW-2NRC', 'P8LD-6VKE'])
            ->take($quantity)
            ->map(fn (string $code): Ticket => new Ticket(['code' => $code]));

        return $order->setRelation('ticketType', $ticketType)->setRelation('tickets', $tickets);
    }
}
