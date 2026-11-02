<?php

namespace Database\Factories;

use App\Models\BarracksRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BarracksRegistration>
 */
class BarracksRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fullName = fake()->name();

        return [
            'full_name' => $fullName,
            'state_of_origin' => fake()->randomElement(['Oyo', 'Ogun', 'Osun', 'Lagos', 'Ondo', 'Kwara']),
            'lga' => fake()->city(),
            'residential_address' => fake()->streetAddress().', Ibadan',
            'occupation' => fake()->jobTitle(),
            'phone' => fake()->unique()->numerify('080########'),
            'passport_path' => 'barracks-passports/'.fake()->uuid().'.jpg',
            'signature_name' => $fullName,
            'declared_at' => now(),
        ];
    }
}
