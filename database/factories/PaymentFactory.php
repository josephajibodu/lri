<?php

namespace Database\Factories;

use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Support\ReadableCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'reference' => fn (array $attributes): string => Order::find($attributes['order_id'])->reference.'-'.ReadableCode::generate(4),
            'provider' => 'bachs',
            'method' => PaymentMethod::BankTransfer,
            'status' => PaymentStatus::Open,
            'amount' => fn (array $attributes): int => Order::find($attributes['order_id'])->amount,
            'currency' => 'NGN',
            'provider_checkout_id' => 'chk_'.fake()->unique()->regexify('[a-z0-9]{20}'),
            'checkout_url' => fake()->url(),
            'expires_at' => now()->addHour(),
        ];
    }

    /**
     * An attempt for the given payment method.
     */
    public function method(PaymentMethod $method): static
    {
        return $this->state(['method' => $method]);
    }

    /**
     * An attempt that collected the money.
     */
    public function succeeded(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Succeeded,
            'provider_payment_id' => 'ch_'.fake()->unique()->regexify('[a-z0-9]{20}'),
            'paid_at' => now(),
        ]);
    }

    /**
     * An attempt that failed for the given reason.
     */
    public function failed(PaymentFailureReason $reason = PaymentFailureReason::Declined): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Failed,
            'failure_reason' => $reason,
            'failed_at' => now(),
        ]);
    }
}
