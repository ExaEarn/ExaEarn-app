<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skills_media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->cascadeOnDelete();
            $table->string('asset_type', 60);
            $table->string('visibility', 30)->default('private');
            $table->string('provider', 60)->default('local');
            $table->string('disk', 60)->default('local');
            $table->string('storage_reference', 500);
            $table->string('safe_filename', 220);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('processing_state', 40)->default('READY');
            $table->json('metadata')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'lesson_id']);
            $table->index(['visibility', 'processing_state']);
        });

        Schema::create('skills_lesson_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->string('status', 32)->default('started');
            $table->unsignedInteger('watch_seconds')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
            $table->index(['user_id', 'course_id', 'status']);
        });

        Schema::create('skills_instructor_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instructor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('asset', 20)->default('USDT');
            $table->decimal('amount', 20, 8);
            $table->string('status', 40)->default('REQUESTED');
            $table->string('destination_type', 40)->default('internal_balance');
            $table->string('reference', 140)->unique();
            $table->string('idempotency_key', 140)->nullable()->unique();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('earning_ids')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['instructor_user_id', 'status']);
        });

        Schema::create('skills_content_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->string('reason', 80);
            $table->text('description')->nullable();
            $table->string('status', 40)->default('OPEN');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'status']);
        });

        Schema::create('skills_reconciliation_incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('incident_type', 80);
            $table->string('severity', 24)->default('medium');
            $table->string('status', 40)->default('OPEN');
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('evidence')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['incident_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skills_reconciliation_incidents');
        Schema::dropIfExists('skills_content_reports');
        Schema::dropIfExists('skills_instructor_payouts');
        Schema::dropIfExists('skills_lesson_progress');
        Schema::dropIfExists('skills_media_assets');
    }
};
