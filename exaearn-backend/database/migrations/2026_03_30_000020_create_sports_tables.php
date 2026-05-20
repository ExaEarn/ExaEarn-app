<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name', 255);
            $table->string('sport', 64);
            $table->string('country', 120);
            $table->unsignedSmallInteger('age');
            $table->string('position', 120)->nullable();
            $table->string('club', 255)->nullable();
            $table->string('profile_photo', 255)->nullable();
            $table->string('highlight_video', 255)->nullable();
            $table->jsonb('performance_statistics')->nullable();
            $table->jsonb('identity_metadata')->nullable();
            $table->boolean('identity_verified')->default(false);
            $table->boolean('is_searchable')->default(true);
            $table->timestamps();

            $table->index(['sport', 'country']);
            $table->index(['user_id', 'is_searchable']);
        });

        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('sport', 64);
            $table->text('description')->nullable();
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->string('status', 32)->default('draft');
            $table->decimal('reward_pool', 20, 8)->default(0);
            $table->boolean('manual_review_required')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['sport', 'status']);
        });

        Schema::create('competition_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 20, 8)->default(0);
            $table->unsignedInteger('ranking')->nullable();
            $table->unsignedInteger('community_votes')->default(0);
            $table->string('status', 32)->default('registered');
            $table->jsonb('verification_metadata')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'athlete_id']);
            $table->index(['competition_id', 'ranking']);
        });

        Schema::create('sponsorships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 20, 8);
            $table->string('status', 32)->default('pending');
            $table->string('milestone', 120)->nullable();
            $table->text('message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['sponsor_id', 'status']);
            $table->index(['athlete_id', 'status']);
        });

        Schema::create('scouting_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->string('sender_role', 32);
            $table->string('status', 32)->default('open');
            $table->string('subject', 255);
            $table->text('message');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['athlete_id', 'status']);
        });

        Schema::create('athlete_leaderboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->string('sport', 64);
            $table->unsignedInteger('competition_wins')->default(0);
            $table->decimal('performance_score', 20, 8)->default(0);
            $table->unsignedInteger('community_votes')->default(0);
            $table->unsignedInteger('sponsorship_count')->default(0);
            $table->decimal('sponsorship_total', 20, 8)->default(0);
            $table->timestamp('updated_at')->useCurrent();

            $table->unique('athlete_id');
            $table->index(['sport', 'performance_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_leaderboards');
        Schema::dropIfExists('scouting_inquiries');
        Schema::dropIfExists('sponsorships');
        Schema::dropIfExists('competition_participants');
        Schema::dropIfExists('competitions');
        Schema::dropIfExists('athletes');
    }
};

