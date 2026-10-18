<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rental_request_histories')) {
            Schema::create('rental_request_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rental_request_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_role', 20);
                $table->string('event', 40);
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['rental_request_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_request_histories');
    }
};
