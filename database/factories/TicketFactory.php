<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Ticket;
use App\Support\ReadableCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->paid(),
            'ticket_type_id' => fn (array $attributes): int => Order::find($attributes['order_id'])->ticket_type_id,
            'code' => ReadableCode::generate(8, groupSize: 4),
            'holder_name' => null,
            'holder_email' => null,
            'checked_in_at' => null,
        ];
    }

    /**
     * A ticket with someone's name and email on it.
     */
    public function named(): static
    {
        return $this->state(fn (): array => [
            'holder_name' => fake()->name(),
            'holder_email' => fake()->unique()->safeEmail(),
        ]);
    }
}
