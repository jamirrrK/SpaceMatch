<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('verification_type', 10);
            $table->string('purpose', 20);
            $table->char('destination_hash', 64)->index();
            $table->text('destination_encrypted');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->useCurrent();
            // Older MariaDB must not infer an invalid zero-date default.
            // Issue() always supplies the five-minute expiry; omitted values
            // default to immediate expiration rather than unlimited validity.
            $table->timestamp('expires_at')->useCurrent()->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose', 'verification_type', 'consumed_at'], 'otp_user_purpose_channel_consumed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
