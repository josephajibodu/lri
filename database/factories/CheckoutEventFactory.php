<?php

namespace Database\Factories;

use App\Enums\CheckoutStep;
use App\Models\CheckoutEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckoutEvent>
 */
class CheckoutEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visitor_id' => fake()->uuid(),
            'order_id' => null,
            'payment_id' => null,
            'step' => CheckoutStep::TicketPageViewed,
            'method' => null,
            'properties' => null,
            'occurred_at' => now(),
        ];
    }
}
