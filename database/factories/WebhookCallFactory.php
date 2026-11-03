<?php

namespace Database\Factories;

use App\Models\WebhookCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookCall>
 */
class WebhookCallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventId = 'evt_'.fake()->unique()->regexify('[a-z0-9]{20}');

        return [
            'provider' => 'bachs',
            'event_id' => $eventId,
            'type' => 'collection.succeeded',
            'payload' => ['id' => $eventId, 'type' => 'collection.succeeded', 'data' => []],
            'signature_valid' => true,
            'event_created_at' => now(),
        ];
    }
}
