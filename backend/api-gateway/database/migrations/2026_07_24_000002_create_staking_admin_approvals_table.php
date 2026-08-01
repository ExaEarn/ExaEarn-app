<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staking_admin_approvals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('approval_type', 64);
            $table->string('status', 32)->default('pending');
            $table->foreignId('requested_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id');
            $table->jsonb('proposed_changes');
            $table->text('reason');
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['approval_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staking_admin_approvals');
    }
};
