<?php

namespace Database\Seeders;

use App\Models\TicketType;
use Illuminate\Database\Seeder;

/**
 * The tickets on sale. Safe to run again in production: it updates the ticket types by slug instead of adding copies.
 */
class TicketTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TicketType::query()->updateOrCreate(['slug' => 'disco-run-standard'], [
            'event' => 'disco-run',
            'name' => 'Silent Disco Run ticket',
            'description' => 'Includes silent disco headphones',
            'price' => 1_000_000,
            'currency' => 'NGN',
            'max_per_order' => 10,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }
}
