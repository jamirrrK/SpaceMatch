<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('reference', 32)->nullable()->unique();
            // A prior owner-role migration may already have added this field.
            if (! Schema::hasColumn('bookings', 'occupants')) {
                $table->unsignedTinyInteger('occupants')->default(1);
            }
            $table->decimal('monthly_rent', 12, 2)->nullable();
            $table->index(['space_id', 'status', 'start_date', 'end_date'], 'bookings_space_status_dates_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_space_status_dates_index');
            $table->dropUnique(['reference']);
            // Keep occupants so rolling back never removes renter occupancy data.
            $table->dropColumn(['reference', 'monthly_rent']);
        });
    }
};
