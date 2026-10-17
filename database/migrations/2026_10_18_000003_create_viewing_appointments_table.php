<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viewing_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('renter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('requested_start_at');
            $table->dateTime('scheduled_start_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('response_reason')->nullable();
            $table->timestamps();
            $table->index(['renter_id', 'status', 'requested_start_at']);
            $table->index(['owner_id', 'status', 'requested_start_at']);
            $table->index(['space_id', 'status', 'scheduled_start_at']);
        });

        Schema::create('viewing_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viewing_appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('activity_type', 30);
            $table->dateTime('scheduled_start_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['viewing_appointment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viewing_activities');
        Schema::dropIfExists('viewing_appointments');
    }
};
