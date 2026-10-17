<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number', 16)->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('verification_required_at')->nullable();
            $table->string('verification_method', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone_number']);
            $table->dropColumn(['phone_number', 'phone_verified_at', 'verification_required_at', 'verification_method']);
        });
    }
};