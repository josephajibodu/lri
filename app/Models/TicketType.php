<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\Money;
use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'event',
    'name',
    'slug',
    'description',
    'price',
    'currency',
    'capacity',
    'max_per_order',
    'sales_start_at',
    'sales_end_at',
    'is_active',
    'sort_order',
])]
class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'capacity' => 'integer',
            'max_per_order' => 'integer',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Limit the query to ticket types that are switched on and inside their sales window right now.
     *
     * @param  Builder<TicketType>  $query
     */
    public function scopeOnSale(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('sales_start_at')->orWhere('sales_start_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('sales_end_at')->orWhere('sales_end_at', '>', now()));
    }

    /**
     * The price of one ticket.
     */
    public function unitPrice(): Money
    {
        return Money::of($this->price, $this->currency);
    }

    /**
     * Tickets taken: those on paid orders, plus those held for orders whose checkout is still open.
     */
    public function ticketsTaken(): int
    {
        return (int) $this->orders()
            ->where(fn (Builder $query) => $query
                ->where('status', OrderStatus::Paid)
                ->orWhere(fn (Builder $query) => $query->where('status', OrderStatus::Pending)->where('expires_at', '>', now())))
            ->sum('quantity');
    }

    /**
     * How many tickets are left, or null when there is no limit.
     */
    public function ticketsLeft(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->ticketsTaken());
    }

    /**
     * The most tickets one order can take right now.
     */
    public function maxQuantity(): int
    {
        return min($this->max_per_order, $this->ticketsLeft() ?? PHP_INT_MAX);
    }

    /**
     * The event's details from config/lri.php (its title, date and venue), such as lri.disco_run for "disco-run".
     *
     * @return array<string, mixed>
     */
    public function eventDetails(): array
    {
        return config('lri.'.str_replace('-', '_', $this->event), []);
    }

    /**
     * When the event starts, written out for buyers (such as Friday 11 December, 6:00 PM), or its date window
     * while no start is set.
     */
    public function eventWhen(): ?string
    {
        $event = $this->eventDetails();

        return filled($event['starts_at'] ?? null)
            ? Carbon::parse($event['starts_at'], $event['timezone'])->format('l j F, g:i A')
            : ($event['date_window'] ?? null);
    }
}
