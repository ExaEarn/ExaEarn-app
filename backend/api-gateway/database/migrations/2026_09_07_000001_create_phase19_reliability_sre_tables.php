<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sre_service_registry', function (Blueprint $table): void {
            $table->id();
            $table->uuid('service_uuid')->unique();
            $table->string('service_id', 120)->unique();
            $table->string('service_name', 160);
            $table->string('service_type', 80)->index();
            $table->string('criticality', 24)->index();
            $table->string('environment', 32)->index();
            $table->string('version', 80)->nullable();
            $table->string('deployment_id', 120)->nullable();
            $table->string('region', 80)->nullable();
            $table->json('dependencies')->nullable();
            $table->string('health_endpoint', 255)->nullable();
            $table->string('readiness_endpoint', 255)->nullable();
            $table->timestamp('heartbeat_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('status', 32)->default('UNKNOWN')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('sre_dependency_checks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('check_uuid')->unique();
            $table->string('service_id', 120)->index();
            $table->string('dependency', 120)->index();
            $table->string('dependency_type', 80)->index();
            $table->string('status', 32)->index();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();
        });

        Schema::create('sre_health_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('snapshot_uuid')->unique();
            $table->string('scope', 80)->index();
            $table->string('overall_status', 40)->index();
            $table->json('liveness');
            $table->json('readiness');
            $table->json('dependency_health');
            $table->json('business_readiness');
            $table->json('reason_codes');
            $table->json('impact')->nullable();
            $table->timestamp('captured_at')->index();
            $table->timestamps();
        });

        Schema::create('sre_operational_alerts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('alert_uuid')->unique();
            $table->string('alert_key', 160)->index();
            $table->string('severity', 24)->index();
            $table->string('status', 32)->default('OPEN')->index();
            $table->string('service_id', 120)->nullable()->index();
            $table->string('component', 120)->nullable()->index();
            $table->json('evidence')->nullable();
            $table->timestamp('last_triggered_at')->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['alert_key', 'status']);
        });

        Schema::create('sre_slo_definitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('slo_uuid')->unique();
            $table->string('service_id', 120)->index();
            $table->string('sli_key', 120)->index();
            $table->string('target', 40);
            $table->string('window', 40);
            $table->string('error_budget_policy', 120);
            $table->string('status', 32)->default('ACTIVE')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['service_id', 'sli_key', 'window']);
        });

        Schema::create('sre_queue_states', function (Blueprint $table): void {
            $table->id();
            $table->uuid('queue_uuid')->unique();
            $table->string('queue_name', 120)->index();
            $table->string('classification', 40)->index();
            $table->unsignedInteger('depth')->default(0);
            $table->unsignedInteger('oldest_job_age_seconds')->default(0);
            $table->unsignedInteger('failed_jobs')->default(0);
            $table->string('status', 32)->default('HEALTHY')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();
        });

        Schema::create('sre_worker_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->uuid('worker_uuid')->unique();
            $table->string('worker_id', 160)->unique();
            $table->string('worker_type', 80)->index();
            $table->string('queue_name', 120)->nullable()->index();
            $table->string('version', 80)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable()->index();
            $table->timestamp('last_job_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->string('status', 32)->default('UNKNOWN')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('sre_backup_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('backup_uuid')->unique();
            $table->string('backup_type', 40)->index();
            $table->string('scope', 80)->index();
            $table->string('status', 32)->index();
            $table->string('storage_reference_hash', 128)->nullable();
            $table->string('checksum', 128)->nullable();
            $table->boolean('encrypted')->default(true);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('retention_until')->nullable();
            $table->string('restore_test_status', 40)->default('NOT_RUN')->index();
            $table->timestamp('restore_tested_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('sre_recovery_actions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('action_uuid')->unique();
            $table->string('action_type', 80)->index();
            $table->string('scope', 80)->index();
            $table->string('scope_reference', 180)->nullable()->index();
            $table->string('status', 40)->default('REQUESTED')->index();
            $table->string('requested_by_type', 64)->nullable();
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('reason');
            $table->json('prechecks')->nullable();
            $table->json('result')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sre_recovery_actions');
        Schema::dropIfExists('sre_backup_records');
        Schema::dropIfExists('sre_worker_heartbeats');
        Schema::dropIfExists('sre_queue_states');
        Schema::dropIfExists('sre_slo_definitions');
        Schema::dropIfExists('sre_operational_alerts');
        Schema::dropIfExists('sre_health_snapshots');
        Schema::dropIfExists('sre_dependency_checks');
        Schema::dropIfExists('sre_service_registry');
    }
};
