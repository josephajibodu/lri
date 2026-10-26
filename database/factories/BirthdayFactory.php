<?php

namespace Database\Factories;

use App\Models\Birthday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Birthday>
 */
class BirthdayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $birthDate = fake()->dateTimeBetween('-60 years', '-18 years');

        return [
            'full_name' => fake()->name(),
            'preferred_name' => fake()->optional()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'instagram_handle' => fake()->optional()->userName(),
            'tribe' => fake()->optional()->randomElement(config('lri.tribes')),
            'birth_month' => (int) $birthDate->format('n'),
            'birth_day' => (int) $birthDate->format('j'),
            'birth_year' => fake()->boolean() ? (int) $birthDate->format('Y') : null,
            'notes' => fake()->optional()->sentence(),
            'photo_paths' => [],
            'photo_consent' => true,
        ];
    }

    /**
     * Indicate that the birthday falls on the given month and day.
     */
    public function on(int $month, int $day): static
    {
        return $this->state(fn (array $attributes) => [
            'birth_month' => $month,
            'birth_day' => $day,
        ]);
    }
}
