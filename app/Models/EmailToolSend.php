<?php

namespace App\Models;

use App\Enums\EmailToolEvent;
use App\Enums\EmailToolSendStatus;
use Database\Factories\EmailToolSendFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of news about an order's buyer for the email tool, kept so organisers can see what went out and what failed.
 */
#[Fillable([
    'order_id',
    'tool',
    'event',
    'status',
    'attempts',
    'error',
    'sent_at',
    'failed_at',
])]
class EmailToolSend extends Model
{
    /** @use HasFactory<EmailToolSendFactory> */
    use HasFactory;

    /**
     * The default values for attributes, so a new send reads as waiting before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'waiting',
        'attempts' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => EmailToolEvent::class,
            'status' => EmailToolSendStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
