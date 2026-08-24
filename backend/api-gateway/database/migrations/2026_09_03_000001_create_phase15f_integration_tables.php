<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phase15_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_uuid')->unique();
            $table->string('scope', 80)->default('GLOBAL');
            $table->string('status', 40);
            $table->unsignedInteger('difference_count')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('phase15_reconciliation_differences', function (Blueprint $table): void {
            $table->id();
            $table->uuid('difference_uuid')->unique();
            $table->foreignId('run_id')->constrained('phase15_reconciliation_runs')->cascadeOnDelete();
            $table->string('module', 40);
            $table->string('severity', 24)->default('WARNING');
            $table->string('code', 100);
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 100)->nullable();
            $table->json('evidence')->nullable();
            $table->string('status', 40)->default('OPEN');
            $table->timestamps();
            $table->index(['module', 'severity']);
        });

        Schema::create('phase15_emergency_controls', function (Blueprint $table): void {
            $table->id();
            $table->uuid('control_uuid')->unique();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('scope', 40);
            $table->string('scope_reference', 120)->nullable();
            $table->string('control', 80);
            $table->string('status', 40)->default('ACTIVE');
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->text('reason');
            $table->timestamp('activated_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['scope', 'scope_reference', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase15_emergency_controls');
        Schema::dropIfExists('phase15_reconciliation_differences');
        Schema::dropIfExists('phase15_reconciliation_runs');
    }
};
