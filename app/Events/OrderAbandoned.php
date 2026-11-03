<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An order wasn't paid before its hold ran out, so its tickets went back on sale.
 */
class OrderAbandoned
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
