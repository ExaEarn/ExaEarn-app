<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('skills_subscriptions', 'idempotency_key')) {
                $table->string('idempotency_key', 120)->nullable()->after('metadata');
                $table->unique(['user_id', 'idempotency_key']);
            }
            if (! Schema::hasColumn('skills_subscriptions', 'pricing_snapshot')) {
                $table->json('pricing_snapshot')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('skills_subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('ends_at');
            }
            if (! Schema::hasColumn('skills_subscriptions', 'cancels_at')) {
                $table->timestamp('cancels_at')->nullable()->after('ends_at');
            }
            if (! Schema::hasColumn('skills_subscriptions', 'renewal_reference')) {
                $table->string('renewal_reference')->nullable()->after('settlement_asset');
            }
        });

        Schema::table('instructor_profiles', function (Blueprint $table): void {
            foreach ([
                'legal_name', 'entity_type', 'country', 'tax_residency', 'tax_status',
                'withholding_status', 'tax_policy_version', 'tax_verification_status',
            ] as $column) {
                if (! Schema::hasColumn('instructor_profiles', $column)) {
                    $table->string($column)->nullable();
                }
            }
            if (! Schema::hasColumn('instructor_profiles', 'tax_identifier_hash')) {
                $table->string('tax_identifier_hash')->nullable();
            }
            if (! Schema::hasColumn('instructor_profiles', 'tax_documents')) {
                $table->json('tax_documents')->nullable();
            }
        });

        Schema::table('skills_opportunities', function (Blueprint $table): void {
            foreach ([
                'organization_id', 'employment_type', 'remote_policy', 'experience_level',
                'review_status', 'published_at', 'paused_at', 'closed_at',
            ] as $column) {
                if (! Schema::hasColumn('skills_opportunities', $column)) {
                    str_ends_with($column, '_at')
                        ? $table->timestamp($column)->nullable()
                        : $table->string($column)->nullable();
                }
            }
            if (! Schema::hasColumn('skills_opportunities', 'preferred_skills')) {
                $table->json('preferred_skills')->nullable();
            }
            if (! Schema::hasColumn('skills_opportunities', 'required_credentials')) {
                $table->json('required_credentials')->nullable();
            }
            if (! Schema::hasColumn('skills_opportunities', 'moderation')) {
                $table->json('moderation')->nullable();
            }
        });

        Schema::table('skills_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('skills_applications', 'verified_skills_snapshot')) {
                $table->json('verified_skills_snapshot')->nullable();
            }
            if (! Schema::hasColumn('skills_applications', 'answers')) {
                $table->json('answers')->nullable();
            }
            if (! Schema::hasColumn('skills_applications', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
        });

        Schema::create('skills_tax_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('country', 3)->nullable();
            $table->string('entity_type', 40)->nullable();
            $table->string('income_category', 80)->default('instructor_payout');
            $table->string('payout_asset', 20)->nullable();
            $table->string('outcome', 40)->default('MANUAL_REVIEW');
            $table->decimal('withholding_rate', 18, 8)->default('0');
            $table->string('policy_version', 80);
            $table->string('status', 40)->default('DRAFT');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['country', 'entity_type', 'status']);
        });

        Schema::create('skills_organizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('country', 3)->nullable();
            $table->string('industry', 120)->nullable();
            $table->string('status', 40)->default('PENDING');
            $table->string('kyb_status', 40)->default('PENDING');
            $table->string('billing_status', 40)->default('NOT_CONFIGURED');
            $table->string('plan_code', 60)->default('BUSINESS');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('skills_organization_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('skills_organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 180)->nullable();
            $table->string('role', 40)->default('LEARNER');
            $table->string('status', 40)->default('PENDING');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'email']);
        });

        Schema::create('skills_business_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('skills_organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('AVAILABLE');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('skills_training_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('skills_organizations')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->json('required_course_ids')->nullable();
            $table->json('optional_course_ids')->nullable();
            $table->string('status', 40)->default('DRAFT');
            $table->timestamp('deadline_at')->nullable();
            $table->timestamps();
        });

        Schema::create('skills_course_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('skills_organizations')->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('skills_training_programs')->nullOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 40)->default('ASSIGNED');
            $table->decimal('progress_percentage', 8, 2)->default('0');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'course_id', 'assigned_to_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skills_course_assignments');
        Schema::dropIfExists('skills_training_programs');
        Schema::dropIfExists('skills_business_seats');
        Schema::dropIfExists('skills_organization_members');
        Schema::dropIfExists('skills_organizations');
        Schema::dropIfExists('skills_tax_policies');
    }
};
