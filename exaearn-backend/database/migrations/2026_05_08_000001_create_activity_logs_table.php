<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('type', 64); // auth, wallet, trade, reward, staking, nft, admin, security, system
            $table->string('action', 120); // login, logout, withdrawal, deposit, order_created, etc
            $table->string('ip', 45)->nullable(); // IPv4 or IPv6
            $table->text('device')->nullable(); // User agent string
            $table->json('data')->nullable(); // amount, asset, pair, etc
            $table->string('status', 32)->default('success'); // success, failed, pending
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('admin_id');
            $table->index('type');
            $table->index('action');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
