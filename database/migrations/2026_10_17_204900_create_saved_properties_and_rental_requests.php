<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'property_id']);
        });

        Schema::create('rental_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->date('move_in_date');
            $table->unsignedTinyInteger('occupants');
            $table->unsignedSmallInteger('duration_months');
            $table->string('contact_phone', 30);
            $table->text('message')->nullable();
            $table->text('renter_response')->nullable();
            $table->string('status', 40)->default('pending');
            $table->text('response_message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['renter_id', 'status']);
            $table->index(['space_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_requests');
        Schema::dropIfExists('saved_properties');
    }
};
