<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\PaymentChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentChannel>
 */
class PaymentChannelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'method' => PaymentMethod::Card,
            'currency' => 'NGN',
            'provider' => 'bachs',
            'label' => null,
            'description' => null,
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * A channel for the given payment method.
     */
    public function method(PaymentMethod $method): static
    {
        return $this->state(['method' => $method]);
    }

    /**
     * A channel shown prominently at checkout.
     */
    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    /**
     * A channel that is switched off.
     */
    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }
}
