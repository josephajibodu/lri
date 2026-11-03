<?php

namespace App\Models;

use App\Support\ReadableCode;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One admission, issued once its order is paid.
 */
#[Fillable([
    'order_id',
    'ticket_type_id',
    'code',
    'holder_name',
    'checked_in_at',
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
                do {
                    $ticket->code = ReadableCode::generate(8, groupSize: 4);
                } while (static::query()->where('code', $ticket->code)->exists());
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
            'checked_in_at' => 'datetime',
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
}
