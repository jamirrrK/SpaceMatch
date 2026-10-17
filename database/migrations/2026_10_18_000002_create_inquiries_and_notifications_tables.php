<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('renter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->index(['renter_id', 'status', 'updated_at']);
            $table->index(['owner_id', 'status', 'updated_at']);
        });

        Schema::create('inquiry_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('activity_type', 20);
            $table->text('body')->nullable();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['inquiry_id', 'created_at']);
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('inquiry_activities');
        Schema::dropIfExists('inquiries');
    }
};
