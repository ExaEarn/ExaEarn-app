<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('developer_organizations', function (Blueprint $table): void {
            $table->foreignId('institution_id')->nullable()->after('workspace_id')->constrained('institutional_accounts')->restrictOnDelete();
            $table->string('authorized_representative_status', 30)->default('not_verified')->after('verification_status');
        });

        Schema::create('developer_production_access_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->foreignId('project_id')->constrained('developer_projects')->cascadeOnDelete();
            $table->foreignId('environment_id')->constrained('developer_project_environments')->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('applicant_type', 20);
            $table->string('use_case', 50);
            $table->string('status', 30)->default('draft')->index();
            $table->string('jurisdiction', 2)->nullable()->index();
            $table->json('request_context');
            $table->string('idempotency_key', 100);
            $table->unsignedInteger('version')->default(1);
            $table->text('developer_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'idempotency_key']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('developer_production_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('developer_production_access_requests')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('developer_projects')->cascadeOnDelete();
            $table->string('capability', 80);
            $table->string('status', 30)->default('pending')->index();
            $table->string('reason_code', 80)->nullable();
            $table->json('limits')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['request_id', 'capability']);
            $table->index(['project_id', 'capability', 'status'], 'developer_production_capability_effective');
        });

        Schema::create('developer_production_access_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('request_id')->constrained('developer_production_access_requests')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('public_message')->nullable();
            $table->text('internal_note')->nullable();
            $table->json('context')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->timestamps();
            $table->unique(['request_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_production_access_reviews');
        Schema::dropIfExists('developer_production_capabilities');
        Schema::dropIfExists('developer_production_access_requests');
        Schema::table('developer_organizations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('institution_id');
            $table->dropColumn('authorized_representative_status');
        });
    }
};
