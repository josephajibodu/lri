<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Everything here also runs in production, so don't use factories.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            TicketTypeSeeder::class,
            PaymentChannelSeeder::class,
        ]);
    }
}
