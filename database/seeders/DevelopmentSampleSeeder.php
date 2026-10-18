<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DevelopmentSampleSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('Synthetic SpaceMatch records may only be seeded in an isolated testing environment.');
        }

        DB::transaction(function (): void {
            $owner = $this->demoUser('demo-owner@spacematch.test', 'Demo Property Owner', User::ROLE_OWNER);
            $renter = $this->demoUser('demo-renter@spacematch.test', 'Demo Renter', User::ROLE_RENTER);

            $property = Property::firstOrCreate(
                ['owner_id' => $owner->id, 'property_name' => 'Sample SpaceMatch Residence'],
                [
                    'is_synthetic' => true, 'data_source' => 'development_sample_seeder',
                    'description' => 'Synthetic listing used for local development.',
                    'address' => '100 Example Street',
                    'barangay' => 'Poblacion',
                    'city' => 'Cebu City',
                    'province' => 'Cebu',
                    'postal_code' => '6000',
                    'property_type' => 'apartment',
                    'status' => 'active',
                    'listing_status' => 'published',
                ],
            );

            $space = Space::firstOrCreate(
                ['property_id' => $property->id, 'space_name' => 'Sample Room 1'],
                [
                    'space_type' => 'studio_unit',
                    'description' => 'Synthetic room record for local development.',
                    'price' => 12500,
                    'capacity' => 2,
                    'availability_status' => 'available',
                    'floor_area' => 24,
                    'floor_number' => 2,
                    'listing_status' => 'published',
                ],
            );

            $booking = Booking::firstOrCreate(
                ['renter_id' => $renter->id, 'space_id' => $space->id, 'status' => 'pending'],
                [
                    'start_date' => now()->addMonth()->toDateString(),
                    'end_date' => now()->addYear()->toDateString(),
                ],
            );

            Payment::firstOrCreate(
                ['booking_id' => $booking->id],
                ['amount' => 12500, 'payment_status' => 'pending'],
            );
        });
    }

    private function demoUser(string $email, string $name, string $role): User
    {
        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->forceFill([
                'name' => $name,
                'role' => $role,
                'password' => Hash::make(Str::random(64)),
                'verification_required_at' => null,
            ])->save();
        }

        return $user;
    }
}
