<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_applications', function (Blueprint $table): void {
            $table->unsignedInteger('application_revision')->default(1)->after('integration_status');
            $table->unsignedInteger('recommended_revision')->nullable()->after('recommended_at');
            $table->unsignedInteger('approved_revision')->nullable()->after('approved_at');
            $table->string('governance_fingerprint', 64)->nullable()->after('review_summary');
        });

        Schema::table('listing_reviews', function (Blueprint $table): void {
            $table->unsignedInteger('application_revision')->nullable()->after('status');
            $table->string('configuration_fingerprint', 64)->nullable()->after('application_revision');
            $table->timestamp('assigned_at')->nullable()->after('reviewer_admin_id');
            $table->foreignId('assigned_by_admin_id')->nullable()->after('assigned_at')->constrained('admins')->nullOnDelete();
            $table->text('assignment_reason')->nullable()->after('assigned_by_admin_id');
        });

        Schema::create('listing_review_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('assignment_uuid')->unique();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            $table->string('review_domain', 64);
            $table->foreignId('reviewer_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('assigned_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->string('status', 32)->default('ACTIVE')->index();
            $table->text('reassignment_reason')->nullable();
            $table->timestamps();
            $table->index(['application_id', 'review_domain', 'status'], 'listing_review_assignment_status_idx');
            $table->index(['reviewer_admin_id', 'review_domain']);
        });

        Schema::create('listing_review_decision_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            $table->foreignId('review_id')->nullable()->constrained('listing_reviews')->nullOnDelete();
            $table->string('review_domain', 64);
            $table->foreignId('reviewer_admin_id')->constrained('admins')->restrictOnDelete();
            $table->string('decision', 64);
            $table->text('reason')->nullable();
            $table->json('evidence')->nullable();
            $table->unsignedInteger('application_revision');
            $table->string('configuration_fingerprint', 64)->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique(['review_id', 'reviewer_admin_id', 'application_revision', 'decision'], 'listing_review_decision_once_unique');
            $table->index(['application_id', 'review_domain', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_review_decision_events');
        Schema::dropIfExists('listing_review_assignments');

        Schema::table('listing_reviews', function (Blueprint $table): void {
            $table->dropForeign(['assigned_by_admin_id']);
            $table->dropColumn(['application_revision', 'configuration_fingerprint', 'assigned_at', 'assigned_by_admin_id', 'assignment_reason']);
        });

        Schema::table('listing_applications', function (Blueprint $table): void {
            $table->dropColumn(['application_revision', 'recommended_revision', 'approved_revision', 'governance_fingerprint']);
        });
    }
};
