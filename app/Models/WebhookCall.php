<?php

namespace App\Models;

use Database\Factories\WebhookCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A notification a payment provider sent us, stored before it is handled so each one is handled once
 * and can be replayed when something goes wrong.
 */
#[Fillable([
    'provider',
    'event_id',
    'type',
    'payload',
    'signature_valid',
    'event_created_at',
    'processed_at',
    'failed_at',
    'error',
])]
class WebhookCall extends Model
{
    /** @use HasFactory<WebhookCallFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'event_created_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
