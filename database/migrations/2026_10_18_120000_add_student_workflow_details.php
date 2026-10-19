<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_student')->default(false);
        });
        Schema::table('renter_preferences', function (Blueprint $table) {
            $table->string('campus', 150)->nullable();
            $table->decimal('max_distance_km', 6, 2)->nullable();
            $table->json('preferred_types')->nullable();
            $table->json('preferred_amenity_ids')->nullable();
        });
        Schema::table('spaces', function (Blueprint $table) {
            $table->boolean('student_friendly')->default(false)->index();
            $table->string('nearby_school', 150)->nullable();
            $table->decimal('campus_distance_km', 6, 2)->nullable();
            $table->string('furnishing', 30)->nullable();
            $table->text('utilities_notes')->nullable();
            $table->text('transport_notes')->nullable();
        });
        Schema::table('rental_requests', function (Blueprint $table) {
            $table->json('terms_snapshot')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
        });
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->string('evidence_path')->nullable();
        });
        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('moderation_reason', 500)->nullable();
        });
    }

    // Production recovery must use a reviewed forward plan; this migration preserves data.
    public function down(): void
    {
        throw new RuntimeException('Student workflow columns hold historical data. Prepare a reviewed forward recovery migration.');
    }
};
