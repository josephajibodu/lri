<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\Money;
use App\Support\ReadableCode;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A buyer's ticket order. It can have several payment attempts, and its tickets are issued once one succeeds.
 */
#[Fillable([
    'reference',
    'ticket_type_id',
    'quantity',
    'unit_price',
    'amount',
    'currency',
    'status',
    'buyer_name',
    'buyer_email',
    'buyer_phone',
    'marketing_consent',
    'visitor_id',
    'attribution',
    'expires_at',
    'paid_at',
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
    ];

    /**
     * Give each new order a short reference buyers can quote, such as LRI-7K3Q9P.
     */
    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (blank($order->reference)) {
                do {
                    $order->reference = 'LRI-'.ReadableCode::generate(6);
                } while (static::query()->where('reference', $order->reference)->exists());
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
            'marketing_consent' => 'boolean',
            'attribution' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
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
     * The price of one ticket when the order was placed.
     */
    public function unitPrice(): Money
    {
        return Money::of($this->unit_price, $this->currency);
    }

    /**
     * What the buyer owes for the whole order.
     */
    public function total(): Money
    {
        return Money::of($this->amount, $this->currency);
    }
}
