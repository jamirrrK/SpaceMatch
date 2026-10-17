<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->state(['role' => User::ROLE_OWNER]),
            'property_name' => fake()->unique()->company().' Residences',
            'property_type' => 'apartment',
            'description' => fake()->paragraph(),
            'address' => fake()->streetAddress(),
            'barangay' => 'Poblacion',
            'city' => 'Cebu City',
            'province' => 'Cebu',
            'postal_code' => '6000',
            'status' => 'active',
            'listing_status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['listing_status' => 'published']);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => 'archived']);
    }
}
