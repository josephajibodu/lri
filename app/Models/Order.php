<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\Money;
use App\Support\ReadableCode;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A buyer's ticket order. It can have several payment attempts, and its tickets are issued once one succeeds.
 */
#[Fillable([
    'reference',
    'receipt_token',
    'ticket_type_id',
    'quantity',
    'unit_price',
    'amount',
    'coupon_id',
    'discount_amount',
    'currency',
    'status',
    'review_note',
    'buyer_name',
    'buyer_email',
    'buyer_phone',
    'marketing_consent',
    'visitor_id',
    'ip_address',
    'attribution',
    'expires_at',
    'paid_at',
    'tickets_emailed_at',
    'abandoned_at',
    'refunded_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * The default values for attributes, so a new order reads as pending before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'marketing_consent' => false,
        'discount_amount' => 0,
    ];

    /**
     * Give each new order a short reference buyers can quote, such as LRI-7K3Q9P, and a long secret
     * code for its receipt address, which no one can guess from the reference.
     */
    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (blank($order->reference)) {
                do {
                    $order->reference = 'LRI-'.ReadableCode::generate(6);
                } while (static::query()->where('reference', $order->reference)->exists());
            }

            if (blank($order->receipt_token)) {
                $order->receipt_token = Str::random(32);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'amount' => 'integer',
            'discount_amount' => 'integer',
            'marketing_consent' => 'boolean',
            'attribution' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'tickets_emailed_at' => 'datetime',
            'abandoned_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * The coupon that took money off this order, if any.
     *
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The most recent payment attempt.
     *
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Every recorded step of the buyer's journey for this order, oldest first.
     *
     * @return HasMany<CheckoutEvent, $this>
     */
    public function checkoutEvents(): HasMany
    {
        return $this->hasMany(CheckoutEvent::class)->oldest('occurred_at')->oldest('id');
    }

    /**
     * Limit the query to orders that count against a limit: paid, disputed (the tickets may yet stand), or held while
     * their checkout is still open.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeHoldingTickets(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Disputed])
            ->orWhere(fn (Builder $query) => $query->unpaidHold()));
    }

    /**
     * Limit the query to orders holding tickets without having paid for them yet.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeUnpaidHold(Builder $query): void
    {
        $query->where('status', OrderStatus::Pending)->where('expires_at', '>', now());
    }

    /**
     * Leave a note for organisers about something on this order a person should look at. The same note isn't added twice.
     */
    public function flagForReview(string $reason): void
    {
        if (! str_contains((string) $this->review_note, $reason)) {
            $this->update(['review_note' => trim($this->review_note."\n".$reason)]);
        }
    }

    /**
     * The price of one ticket when the order was placed.
     */
    public function unitPrice(): Money
    {
        return Money::of($this->unit_price, $this->currency);
    }

    /**
     * The tickets' full price, before any coupon.
     */
    public function subtotal(): Money
    {
        return $this->unitPrice()->times($this->quantity);
    }

    /**
     * What the coupon took off.
     */
    public function discount(): Money
    {
        return Money::of($this->discount_amount, $this->currency);
    }

    /**
     * What the buyer owes for the whole order, after any coupon.
     */
    public function total(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    /**
     * The buyer's link to their order, signed so only someone who was given it can open it.
     *
     * @param  array<string, string>  $query
     */
    public function statusUrl(array $query = []): string
    {
        return $this->signedUrl('orders.show', $query);
    }

    /**
     * The buyer's lasting link to their paid order's receipt and tickets.
     */
    public function receiptUrl(): string
    {
        return route('receipts.show', $this->receipt_token);
    }

    /**
     * A signed link to one of the order's own routes.
     *
     * @param  array<string, string>  $query
     */
    public function signedUrl(string $route, array $query = []): string
    {
        return URL::signedRoute($route, ['order' => $this->reference, ...$query]);
    }
}
