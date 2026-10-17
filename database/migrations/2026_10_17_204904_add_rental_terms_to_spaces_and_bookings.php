<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->unsignedTinyInteger('advance_months')->nullable();
            $table->text('house_rules')->nullable();
            $table->text('move_in_requirements')->nullable();
            $table->text('cancellation_policy')->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->unsignedTinyInteger('advance_months')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount', 'advance_months']);
        });
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount', 'advance_months', 'house_rules', 'move_in_requirements', 'cancellation_policy']);
        });
    }
};
