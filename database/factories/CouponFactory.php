<?php

namespace Database\Factories;

use App\Enums\CouponDiscountType;
use App\Models\Coupon;
use App\Support\ReadableCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state: 20% off, with no limits.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => ReadableCode::generate(8),
            'discount_type' => CouponDiscountType::Percentage,
            'discount_value' => 20,
            'currency' => 'NGN',
        ];
    }

    /**
     * A coupon that takes a fixed amount, in kobo, off the order.
     */
    public function fixedAmount(int $kobo): static
    {
        return $this->state(['discount_type' => CouponDiscountType::FixedAmount, 'discount_value' => $kobo]);
    }

    /**
     * A coupon an organiser has switched off.
     */
    public function disabled(): static
    {
        return $this->state(fn (): array => ['disabled_at' => now()]);
    }
}
