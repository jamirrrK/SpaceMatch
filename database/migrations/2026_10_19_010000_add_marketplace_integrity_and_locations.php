<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->boolean('is_synthetic')->default(false)->index();
            $table->string('data_source', 80)->nullable();
            $table->string('region_code', 10)->nullable()->index();
            $table->string('province_code', 10)->nullable()->index();
            $table->string('city_code', 10)->nullable()->index();
            $table->string('barangay_code', 10)->nullable()->index();
            $table->string('location_visibility', 20)->default('approximate');
            $table->timestamp('location_confirmed_at')->nullable();
            $table->index(['latitude', 'longitude']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('terms_version', 50)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('privacy_version', 50)->nullable();
            $table->timestamp('privacy_acknowledged_at')->nullable();
            $table->string('owner_type', 20)->nullable();
            $table->string('organization_name', 180)->nullable();
            $table->string('preferred_location', 150)->nullable();
        });
        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 500);
            $table->timestamps();
            $table->unique(['review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['terms_version', 'terms_accepted_at', 'privacy_version', 'privacy_acknowledged_at', 'owner_type', 'organization_name', 'preferred_location']));
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropColumn(['is_synthetic', 'data_source', 'region_code', 'province_code', 'city_code', 'barangay_code', 'location_visibility', 'location_confirmed_at']);
        });
    }
};
