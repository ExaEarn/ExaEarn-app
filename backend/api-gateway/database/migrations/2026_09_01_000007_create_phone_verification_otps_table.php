<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('phone_verification_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->index();
            $table->string('phone', 20)->index()->comment('Phone number being verified');
            $table->string('country_code', 3)->comment('ISO 3166-1 alpha-3 country code');
            $table->string('otp_hash', 255)->comment('Hashed OTP (never store plaintext)');
            $table->integer('otp_length')->default(6);
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('expires_at')->index()->comment('OTP expiration time');
            $table->timestamp('verified_at')->nullable()->comment('When OTP was successfully verified');
            $table->integer('attempt_count')->default(0)->comment('Failed verification attempts');
            $table->integer('resend_count')->default(0)->comment('Number of times OTP was resent');
            $table->string('status')->default('pending')->index()->comment('pending, verified, expired, max_attempts');
            $table->json('metadata')->nullable()->comment('Additional verification context');
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            
            // Composite index for efficient querying
            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_verification_otps');
    }
};
