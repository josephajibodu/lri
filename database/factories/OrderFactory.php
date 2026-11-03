<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TicketType;
use App\Support\ReadableCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);

        return [
            'reference' => 'LRI-'.ReadableCode::generate(6),
            'ticket_type_id' => TicketType::factory(),
            'quantity' => $quantity,
            'unit_price' => 1_000_000,
            'amount' => 1_000_000 * $quantity,
            'currency' => 'NGN',
            'status' => OrderStatus::Pending,
            'buyer_name' => fake()->name(),
            'buyer_email' => fake()->unique()->safeEmail(),
            'buyer_phone' => fake()->numerify('080########'),
            'marketing_consent' => fake()->boolean(),
            'visitor_id' => fake()->uuid(),
            'attribution' => null,
            'expires_at' => now()->addHour(),
        ];
    }

    /**
     * An order that has been paid for.
     */
    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    /**
     * An order the buyer left without paying.
     */
    public function abandoned(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Abandoned,
            'expires_at' => now()->subMinute(),
            'abandoned_at' => now(),
        ]);
    }
}
