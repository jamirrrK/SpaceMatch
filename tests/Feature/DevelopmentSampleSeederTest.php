<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\DevelopmentSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevelopmentSampleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_development_sample_seeder_is_repeatable_and_does_not_create_an_admin(): void
    {
        $this->seed(DevelopmentSampleSeeder::class);
        $this->seed(DevelopmentSampleSeeder::class);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseCount('spaces', 1);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(0, User::query()->where('role', User::ROLE_ADMIN)->count());

        $this->assertTrue(Property::query()->first()->spaces()->first()->publiclyListed()->exists());
        $this->assertSame('pending', Booking::query()->first()->status);
        $this->assertSame('pending', Payment::query()->first()->payment_status);
        $this->assertSame('published', Space::query()->first()->listing_status);
    }

    public function test_default_database_seeder_skips_sample_records_outside_local_environment(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('properties', 0);
    }
}
