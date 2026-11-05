<?php

namespace App\Models;

use App\Enums\CouponDiscountType;
use App\Enums\CouponStatus;
use App\Exceptions\CouponUnavailable;
use App\Support\Money;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * A code that takes money off an order's total. Its terms never change once it's created: it can only be disabled,
 * so every order that used a coupon was priced by the terms still on record.
 */
#[Fillable([
    'code',
    'note',
    'discount_type',
    'discount_value',
    'currency',
    'starts_at',
    'ends_at',
    'max_orders',
    'max_tickets_per_order',
])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /**
     * The default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'NGN',
    ];

    /**
     * Refuse any change to a coupon except switching it off or on.
     */
    protected static function booted(): void
    {
        static::updating(function (Coupon $coupon): void {
            if (array_diff(array_keys($coupon->getDirty()), ['disabled_at', 'updated_at']) !== []) {
                throw new LogicException('A coupon can’t be changed once it’s created. Disable it and create a new one.');
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
            'discount_type' => CouponDiscountType::class,
            'discount_value' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_orders' => 'integer',
            'max_tickets_per_order' => 'integer',
            'disabled_at' => 'datetime',
        ];
    }

    /**
     * Codes are kept in capitals, so buyers can type them in any case.
     *
     * @return Attribute<never, string>
     */
    protected function code(): Attribute
    {
        return Attribute::set(fn (string $value): string => self::normalizeCode($value));
    }

    public static function normalizeCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    /**
     * @param  Builder<Coupon>  $query
     */
    public function scopeWithCode(Builder $query, string $code): void
    {
        $query->where('code', self::normalizeCode($code));
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The tickets this coupon works on. None means every ticket.
     *
     * @return BelongsToMany<TicketType, $this>
     */
    public function ticketTypes(): BelongsToMany
    {
        return $this->belongsToMany(TicketType::class);
    }

    /**
     * How many orders count against the coupon's limit: paid ones and ones still being paid for. An abandoned or
     * refunded order gives its use back.
     */
    public function timesTaken(): int
    {
        return $this->orders_taken_count ?? $this->orders()->holdingTickets()->count();
    }

    public function status(): CouponStatus
    {
        return match (true) {
            $this->disabled_at !== null => CouponStatus::Disabled,
            $this->ends_at?->isPast() === true => CouponStatus::Ended,
            $this->starts_at?->isFuture() === true => CouponStatus::Scheduled,
            $this->max_orders !== null && $this->timesTaken() >= $this->max_orders => CouponStatus::UsedUp,
            default => CouponStatus::Active,
        };
    }

    /**
     * Why this coupon can't be used on a ticket right now, or null when it can.
     */
    public function whyUnusableOn(TicketType $ticketType): ?CouponUnavailable
    {
        $problem = match ($this->status()) {
            CouponStatus::Disabled, CouponStatus::Ended => CouponUnavailable::ended(),
            CouponStatus::Scheduled => CouponUnavailable::notStarted(),
            CouponStatus::UsedUp => CouponUnavailable::usedUp(),
            CouponStatus::Active => null,
        };

        if ($problem !== null) {
            return $problem;
        }

        if ($this->discount_type === CouponDiscountType::FixedAmount && $this->currency !== $ticketType->currency) {
            return CouponUnavailable::wrongTicket();
        }

        $onlyOn = $this->ticketTypes()->pluck('ticket_types.id');

        return $onlyOn->isNotEmpty() && ! $onlyOn->contains($ticketType->id) ? CouponUnavailable::wrongTicket() : null;
    }

    /**
     * What this coupon takes off an order. It's worked out on the tickets it covers, and always leaves the smallest
     * amount a provider can charge, because Bachs refuses anything less.
     */
    public function discountFor(Money $unitPrice, int $quantity): Money
    {
        $covered = $unitPrice->times(min($quantity, $this->max_tickets_per_order ?? $quantity))->amount;

        $discount = match ($this->discount_type) {
            CouponDiscountType::Percentage => intdiv($covered * $this->discount_value, 100),
            CouponDiscountType::FixedAmount => min($this->discount_value, $covered),
        };

        $mostItCanTakeOff = max(0, $unitPrice->times($quantity)->amount - config('payments.minimum_charge'));

        return Money::of(min($discount, $mostItCanTakeOff), $unitPrice->currency);
    }

    /**
     * The coupon in words, such as "20% off on up to 2 tickets".
     */
    public function describe(): string
    {
        $discount = match ($this->discount_type) {
            CouponDiscountType::Percentage => "{$this->discount_value}% off",
            CouponDiscountType::FixedAmount => Money::of($this->discount_value, $this->currency)->format().' off',
        };

        return match ($this->max_tickets_per_order) {
            null => $discount,
            1 => "{$discount} on 1 ticket",
            default => "{$discount} on up to {$this->max_tickets_per_order} tickets",
        };
    }
}
