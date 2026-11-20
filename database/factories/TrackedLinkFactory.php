<?php

namespace Database\Factories;

use App\Enums\TrackedLinkDestination;
use App\Enums\TrafficChannel;
use App\Models\TrackedLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackedLink>
 */
class TrackedLinkFactory extends Factory
{
    /**
     * Define the model's default state: an Instagram bio link to the ticket page, never tapped.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Instagram bio',
            'slug' => fake()->unique()->slug(2),
            'source' => TrafficChannel::Instagram,
            'medium' => 'bio',
            'campaign' => null,
            'destination' => TrackedLinkDestination::TicketPage,
        ];
    }

    /**
     * A link that has been tapped.
     */
    public function clicked(int $times = 1): static
    {
        return $this->state(['clicks' => $times, 'last_clicked_at' => now()]);
    }
}
