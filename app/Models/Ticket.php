<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\ReadableCode;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One admission, issued once its order is paid. Each ticket belongs to one person (its holder), who collects one
 * headset with it. The buyer's own ticket is named at payment; the rest are named afterwards by the buyer, by the
 * holder through the ticket's private link, or by staff at the headset desk.
 */
#[Fillable([
    'order_id',
    'ticket_type_id',
    'code',
    'holder_name',
    'holder_email',
    'checked_in_at',
    'checked_in_by',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * Give each new ticket a code to show at the gate, such as 7K3Q-9PXM.
     */
    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            if (blank($ticket->code)) {
                $ticket->code = static::newCode();
            }

            if (blank($ticket->claim_token)) {
                $ticket->claim_token = static::newClaimToken();
            }
        });
    }

    /**
     * A gate code no other ticket has, such as 7K3Q-9PXM.
     */
    public static function newCode(): string
    {
        do {
            $code = ReadableCode::generate(8, groupSize: 4);
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * A long random secret for the ticket's own link. It is never the gate code, so knowing a code doesn't open
     * anyone's ticket.
     */
    public static function newClaimToken(): string
    {
        return Str::random(40);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'link_shared_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * The staff member who handed over this ticket's headset.
     *
     * @return BelongsTo<User, $this>
     */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /**
     * Whether someone's name and email are on this ticket yet.
     */
    public function isNamed(): bool
    {
        return filled($this->holder_name) && filled($this->holder_email);
    }

    /**
     * Whether this ticket's headset has been handed over.
     */
    public function isCollected(): bool
    {
        return $this->checked_in_at !== null;
    }

    /**
     * Whether this ticket still counts: its order is paid and hasn't been refunded.
     */
    public function isValid(): bool
    {
        return $this->order->status === OrderStatus::Paid;
    }

    /**
     * Whether this ticket belongs to the person who bought the order.
     */
    public function isBuyers(): bool
    {
        return Str::lower((string) $this->holder_email) === Str::lower($this->order->buyer_email);
    }

    /**
     * Where the ticket stands, as the buyer sees it on their receipt.
     *
     * @return 'collected'|'yours'|'emailed'|'link_sent'|'no_name'
     */
    public function receiptStatus(): string
    {
        return match (true) {
            $this->isCollected() => 'collected',
            $this->isNamed() && $this->isBuyers() => 'yours',
            $this->isNamed() => 'emailed',
            $this->link_shared_at !== null => 'link_sent',
            default => 'no_name',
        };
    }

    /**
     * The headset desk with this ticket already searched for. The ticket's QR code holds this link, so desk staff
     * can scan it with their phone's camera.
     */
    public function deskUrl(): string
    {
        return url('/admin/headset-desk').'?'.http_build_query(['search' => $this->code]);
    }

    /**
     * The ticket's own private link, for its holder to fill in their details and see their ticket.
     */
    public function claimUrl(): string
    {
        return route('tickets.show', $this->claim_token);
    }
}
