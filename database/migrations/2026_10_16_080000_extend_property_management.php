<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the established address and price columns and every existing record.
        Schema::table('properties', function (Blueprint $table) {
            $table->string('property_type', 40)->default('other');
            $table->string('barangay')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('listing_status', 20)->default('draft');
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_reason')->nullable();
            $table->index(['owner_id', 'created_at']);
            $table->index(['status', 'listing_status', 'city']);
            $table->index('property_type');
        });
        Schema::table('spaces', function (Blueprint $table) {
            $table->decimal('floor_area', 10, 2)->nullable();
            $table->smallInteger('floor_number')->nullable();
            $table->string('listing_status', 20)->default('draft');
            $table->index(['listing_status', 'availability_status', 'price']);
        });
        Schema::create('property_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('image_path')->unique();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['property_id', 'space_id', 'display_order']);
        });
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category', 60);
            $table->timestamps();
        });
        Schema::create('amenity_space', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->unique(['space_id', 'amenity_id']);
        });
        $now = now();
        $amenities = [
            'Wi-Fi' => 'Connectivity', 'Air Conditioning' => 'Comfort',
            'Private Bathroom' => 'Facilities', 'Shared Bathroom' => 'Facilities',
            'Parking' => 'Facilities', 'Kitchen' => 'Facilities', 'Furnished' => 'Comfort',
            'Security' => 'Safety', 'CCTV' => 'Safety', 'Laundry Area' => 'Facilities',
            'Elevator' => 'Accessibility', 'Pet Friendly' => 'Lifestyle',
            'Water Included' => 'Utilities', 'Electricity Included' => 'Utilities',
        ];
        foreach ($amenities as $name => $category) {
            DB::table('amenities')->insert(['name' => $name, 'category' => $category, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_space');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('property_images');
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropIndex(['listing_status', 'availability_status', 'price']);
            $table->dropColumn(['floor_area', 'floor_number', 'listing_status']);
        });
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['owner_id', 'created_at']);
            $table->dropIndex(['status', 'listing_status', 'city']);
            $table->dropIndex(['property_type']);
            $table->dropColumn(['property_type', 'barangay', 'postal_code', 'latitude', 'longitude', 'listing_status', 'moderated_at', 'moderation_reason']);
        });
    }
};
