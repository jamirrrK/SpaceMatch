<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->date('available_from')->nullable();
            $table->index(['bedrooms', 'bathrooms', 'available_from'], 'spaces_discovery_counts_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropIndex('spaces_discovery_counts_date_index');
            $table->dropColumn(['bedrooms', 'bathrooms', 'available_from']);
        });
    }
};
