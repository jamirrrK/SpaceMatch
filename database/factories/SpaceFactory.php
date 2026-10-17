<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Space> */
class SpaceFactory extends Factory
{
    protected $model = Space::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'space_name' => 'Unit '.fake()->unique()->numberBetween(100, 99999),
            'space_type' => 'studio_unit',
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(3000, 25000),
            'capacity' => 2,
            'floor_area' => 24,
            'floor_number' => '2',
            'availability_status' => 'available',
            'listing_status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['listing_status' => 'published']);
    }
}
