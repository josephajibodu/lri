<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
