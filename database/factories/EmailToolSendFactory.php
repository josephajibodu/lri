<?php

namespace Database\Factories;

use App\Enums\EmailToolEvent;
use App\Enums\EmailToolSendStatus;
use App\Models\EmailToolSend;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailToolSend>
 */
class EmailToolSendFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tool' => 'mailchimp',
            'event' => EmailToolEvent::CheckoutStarted,
            'status' => EmailToolSendStatus::Waiting,
        ];
    }

    /**
     * News the tool has taken.
     */
    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => EmailToolSendStatus::Sent,
            'attempts' => 1,
            'sent_at' => now(),
        ]);
    }

    /**
     * News the job gave up on.
     */
    public function failed(string $error = 'Mailchimp returned 400: Invalid Resource'): static
    {
        return $this->state(fn (): array => [
            'status' => EmailToolSendStatus::Failed,
            'attempts' => 1,
            'failed_at' => now(),
            'error' => $error,
        ]);
    }
}
