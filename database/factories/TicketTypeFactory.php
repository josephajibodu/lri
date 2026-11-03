<?php

namespace Database\Factories;

use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Standard', 'Early bird', 'Group of four']).' ticket';

        return [
            'event' => 'disco-run',
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'price' => 1_000_000,
            'currency' => 'NGN',
            'capacity' => null,
            'max_per_order' => 10,
            'sales_start_at' => null,
            'sales_end_at' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A ticket type that is switched off.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * A ticket type limited to the given number of tickets.
     */
    public function limitedTo(int $capacity): static
    {
        return $this->state(['capacity' => $capacity]);
    }
}
