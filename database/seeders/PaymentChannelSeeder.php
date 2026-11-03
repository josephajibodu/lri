<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Models\PaymentChannel;
use Illuminate\Database\Seeder;

/**
 * The ways to pay in naira that Bachs offers, with bank transfer featured. Safe to run again in production:
 * it only adds missing channels, so choices admins have made since are kept.
 */
class PaymentChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PaymentChannel::query()->firstOrCreate(
            ['method' => PaymentMethod::BankTransfer, 'currency' => 'NGN'],
            [
                'provider' => 'bachs',
                'description' => 'Send from any Nigerian bank app.',
                'is_enabled' => true,
                'is_featured' => true,
                'sort_order' => 1,
            ],
        );

        PaymentChannel::query()->firstOrCreate(
            ['method' => PaymentMethod::Card, 'currency' => 'NGN'],
            [
                'provider' => 'bachs',
                'description' => 'Visa, Mastercard or Verve.',
                'is_enabled' => true,
                'is_featured' => false,
                'sort_order' => 2,
            ],
        );
    }
}
