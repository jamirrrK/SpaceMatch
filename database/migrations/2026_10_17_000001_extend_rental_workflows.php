<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
        Schema::table('bookings', function (Blueprint $table) {
            $table->text('renter_message')->nullable();
            $table->decimal('agreed_amount', 12, 2)->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->index(['renter_id', 'status']);
        });

        Schema::create('booking_information_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->text('question');
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'created_at']);
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->text('owner_response')->nullable();
            $table->timestamp('resolved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn(['owner_response', 'resolved_at']);
        });
        Schema::dropIfExists('booking_information_requests');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['renter_id', 'status']);
            $table->dropColumn(['renter_message', 'agreed_amount', 'decision_reason', 'decided_at', 'confirmed_at', 'completed_at']);
        });
    }
};
