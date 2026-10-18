<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('ended_early_at')->nullable();
            $table->foreignId('terminated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('termination_reason')->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->unique(['booking_id', 'payment_type', 'due_date'], 'payments_booking_type_due_unique');
        });

        DB::table('payments')->where('payment_status', 'paid')->update([
            'paid_amount' => DB::raw('amount'),
        ]);

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->string('receipt_number', 40)->unique();
            $table->decimal('amount', 12, 2);
            $table->timestamp('received_at');
            $table->string('payment_method', 40);
            $table->string('reference', 100)->nullable();
            $table->timestamps();
            $table->index(['payment_id', 'received_at']);
        });

        Schema::create('rental_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('reminder_key', 160)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reminder_type', 40);
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->index(['user_id', 'reminder_type', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_reminders');
        Schema::dropIfExists('payment_transactions');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_booking_type_due_unique');
            $table->dropColumn('paid_amount');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('terminated_by');
            $table->dropColumn(['ended_early_at', 'termination_reason']);
        });
    }
};
