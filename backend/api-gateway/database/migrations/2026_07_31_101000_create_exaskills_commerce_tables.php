<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skills_course_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('asset', 20)->default('USDT');
            $table->decimal('gross_amount', 20, 8)->default(0);
            $table->decimal('platform_fee_amount', 20, 8)->default(0);
            $table->decimal('instructor_amount', 20, 8)->default(0);
            $table->decimal('commission_rate', 10, 6)->default(0);
            $table->string('status', 40)->default('completed');
            $table->string('reference', 120)->unique();
            $table->string('idempotency_key', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['course_id', 'status']);
        });

        Schema::create('skills_instructor_earnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instructor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('skills_course_purchases')->nullOnDelete();
            $table->string('asset', 20)->default('USDT');
            $table->decimal('gross_amount', 20, 8)->default(0);
            $table->decimal('platform_fee_amount', 20, 8)->default(0);
            $table->decimal('net_amount', 20, 8)->default(0);
            $table->string('status', 40)->default('pending');
            $table->string('reference', 120)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['instructor_user_id', 'status']);
        });

        Schema::create('skills_challenge_escrows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('challenge_id')->constrained('skills_challenges')->cascadeOnDelete();
            $table->foreignId('sponsor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('asset', 20)->default('USDT');
            $table->decimal('amount', 20, 8)->default(0);
            $table->decimal('paid_amount', 20, 8)->default(0);
            $table->string('status', 40)->default('funded');
            $table->string('funding_reference', 120)->unique();
            $table->string('payout_reference', 120)->nullable()->unique();
            $table->string('idempotency_key', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('funded_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['challenge_id', 'sponsor_user_id']);
            $table->unique(['sponsor_user_id', 'idempotency_key']);
            $table->index(['challenge_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skills_challenge_escrows');
        Schema::dropIfExists('skills_instructor_earnings');
        Schema::dropIfExists('skills_course_purchases');
    }
};
