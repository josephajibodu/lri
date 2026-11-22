<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\RecordsAdminActivity;
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
    'admitted_at',
    'admitted_by',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, RecordsAdminActivity;

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

    public function activityLabel(): string
    {
        return "ticket {$this->code}";
    }

    /**
     * The ticket link's secret code is left out.
     *
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return ['code', 'holder_name', 'holder_email', 'checked_in_at', 'admitted_at'];
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
            'admitted_at' => 'datetime',
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
     * The staff member who let this ticket's holder in at the gate.
     *
     * @return BelongsTo<User, $this>
     */
    public function admittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitted_by');
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
     * Whether this ticket has already been scanned in at the gate, so it can't get anyone else in.
     */
    public function isAdmitted(): bool
    {
        return $this->admitted_at !== null;
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
     * The ticket with this code, typed or scanned with or without its dash, in any case. Its QR code holds the desk's
     * link rather than the bare code, so the link is accepted too.
     */
    public static function findByCode(string $scanned): ?self
    {
        parse_str((string) parse_url($scanned, PHP_URL_QUERY), $query);
        $typed = is_string($query['search'] ?? null) ? $query['search'] : $scanned;
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $typed));

        if (strlen($code) !== 8) {
            return null;
        }

        return static::query()->where('code', substr($code, 0, 4).'-'.substr($code, 4))->first();
    }

    /**
     * The ticket's own private link, for its holder to fill in their details and see their ticket.
     */
    public function claimUrl(): string
    {
        return route('tickets.show', $this->claim_token);
    }
}
