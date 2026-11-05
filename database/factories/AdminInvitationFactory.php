<?php

namespace Database\Factories;

use App\Models\AdminInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AdminInvitation>
 */
class AdminInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'token' => AdminInvitation::fingerprint(Str::random(64)),
            'expires_at' => now()->addDay(),
        ];
    }

    /**
     * An invitation whose link has run out.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    /**
     * An invitation the person has said yes to.
     */
    public function accepted(): static
    {
        return $this->state(fn (): array => ['accepted_at' => now()]);
    }

    /**
     * An invitation the person has said no to.
     */
    public function declined(): static
    {
        return $this->state(fn (): array => ['declined_at' => now()]);
    }

    /**
     * An invitation an organiser took back.
     */
    public function cancelled(): static
    {
        return $this->state(fn (): array => ['cancelled_at' => now()]);
    }
}
