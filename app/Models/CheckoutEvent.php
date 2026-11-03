<?php

namespace App\Models;

use App\Enums\CheckoutStep;
use App\Enums\PaymentMethod;
use Database\Factories\CheckoutEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of a buyer's journey, tied to their visitor id before an order exists and to the order after.
 */
#[Fillable([
    'visitor_id',
    'order_id',
    'payment_id',
    'step',
    'method',
    'properties',
    'occurred_at',
])]
class CheckoutEvent extends Model
{
    /** @use HasFactory<CheckoutEventFactory> */
    use HasFactory;

    /**
     * Events are written once and only carry the time they happened.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step' => CheckoutStep::class,
            'method' => PaymentMethod::class,
            'properties' => 'array',
            'occurred_at' => 'datetime',
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
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
