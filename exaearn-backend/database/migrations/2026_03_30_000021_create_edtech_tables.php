<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description');
            $table->string('difficulty', 32);
            $table->unsignedInteger('duration')->default(0);
            $table->string('status', 32)->default('draft');
            $table->decimal('reward_amount', 20, 8)->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'difficulty']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->longText('content')->nullable();
            $table->string('video_url', 255)->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('minimum_watch_seconds')->default(0);
            $table->unsignedInteger('order_index');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'order_index']);
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('passing_score');
            $table->unsignedInteger('time_limit');
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->boolean('require_unlock')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->jsonb('options');
            $table->string('correct_answer', 255);
            $table->unsignedInteger('order_index')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('watch_seconds')->default(0);
            $table->unsignedInteger('last_unlocked_lesson_order')->default(1);
            $table->jsonb('progress_metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('score')->default(0);
            $table->boolean('passed')->default(false);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->jsonb('submitted_answers')->nullable();
            $table->string('attempt_fingerprint', 64)->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'quiz_id']);
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_code', 64)->unique();
            $table->timestamp('issued_at');
            $table->string('verification_hash', 64)->unique();
            $table->string('verification_url', 255);
            $table->string('blockchain_reference', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('learning_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->decimal('reward_amount', 20, 8);
            $table->string('reward_token', 16);
            $table->string('status', 32)->default('pending');
            $table->foreignId('reward_id')->nullable()->constrained('user_rewards')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_rewards');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('course_enrollments');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('courses');
    }
};

