<?php

namespace App\Models;

use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Support\Money;
use App\Support\ReadableCode;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One attempt to pay for an order with a provider. Keeping every attempt, failed ones included,
 * is what shows how each payment method performs.
 */
#[Fillable([
    'order_id',
    'reference',
    'provider',
    'method',
    'status',
    'failure_reason',
    'failure_message',
    'amount',
    'currency',
    'fee',
    'provider_checkout_id',
    'provider_payment_id',
    'provider_status',
    'checkout_url',
    'expires_at',
    'paid_at',
    'failed_at',
    'last_checked_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * The default values for attributes, so a new attempt reads as open before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * Give each attempt its own reference for the provider, built from the order's, such as LRI-7K3Q9P-4MXA.
     * Providers such as Bachs reject a reference they have seen before, even on an expired checkout.
     */
    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (blank($payment->reference)) {
                do {
                    $payment->reference = $payment->order->reference.'-'.ReadableCode::generate(4);
                } while (static::query()->where('reference', $payment->reference)->exists());
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
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'failure_reason' => PaymentFailureReason::class,
            'amount' => 'integer',
            'fee' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'last_checked_at' => 'datetime',
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
     * @return HasMany<CheckoutEvent, $this>
     */
    public function checkoutEvents(): HasMany
    {
        return $this->hasMany(CheckoutEvent::class);
    }

    /**
     * The amount this attempt asked for.
     */
    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }
}
