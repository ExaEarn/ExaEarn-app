<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_risk_signals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('signal_uuid')->unique();
            $table->string('signal_type', 96)->index();
            $table->string('source', 96)->index();
            $table->string('subject_type', 64)->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->string('severity', 24)->index();
            $table->decimal('confidence', 5, 4)->default('1.0000');
            $table->string('status', 32)->default('ACTIVE')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('detected_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'status'], 'security_signals_subject_status_idx');
        });

        Schema::create('security_risk_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('decision_uuid')->unique();
            $table->string('subject_type', 64)->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->string('action', 96)->index();
            $table->string('decision', 48)->index();
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->string('risk_level', 24)->index();
            $table->json('reason_codes');
            $table->json('signals');
            $table->string('required_action', 96)->nullable();
            $table->string('decision_version', 80)->default('phase18-v1');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('security_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('case_uuid')->unique();
            $table->string('case_type', 80)->index();
            $table->string('severity', 24)->index();
            $table->string('status', 32)->default('OPEN')->index();
            $table->string('subject_type', 64)->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->json('evidence')->nullable();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
        });

        Schema::create('security_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('incident_uuid')->unique();
            $table->string('category', 80)->index();
            $table->string('severity', 16)->index();
            $table->string('status', 32)->default('DETECTED')->index();
            $table->string('scope_type', 64)->nullable()->index();
            $table->string('scope_reference', 180)->nullable()->index();
            $table->json('timeline')->nullable();
            $table->json('impact')->nullable();
            $table->json('corrective_actions')->nullable();
            $table->foreignId('owner_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('security_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('rule_uuid')->unique();
            $table->string('rule_key', 120)->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('mode', 24)->default('ACTIVE')->index();
            $table->string('action', 64)->default('MONITOR');
            $table->json('configuration');
            $table->text('reason');
            $table->foreignId('changed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('effective_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['rule_key', 'version']);
        });

        Schema::create('security_emergency_controls', function (Blueprint $table): void {
            $table->id();
            $table->uuid('control_uuid')->unique();
            $table->string('control_type', 80)->index();
            $table->string('scope_type', 64)->index();
            $table->string('scope_reference', 180)->nullable()->index();
            $table->string('status', 32)->default('ACTIVE')->index();
            $table->text('reason');
            $table->json('metadata')->nullable();
            $table->foreignId('activated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('deactivated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('security_related_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('link_uuid')->unique();
            $table->unsignedBigInteger('primary_user_id')->index();
            $table->unsignedBigInteger('related_user_id')->index();
            $table->string('relationship_type', 80)->index();
            $table->decimal('confidence', 5, 4)->default('0.5000');
            $table->json('evidence')->nullable();
            $table->string('status', 32)->default('ACTIVE')->index();
            $table->timestamps();
            $table->unique(['primary_user_id', 'related_user_id', 'relationship_type'], 'security_related_accounts_unique');
        });

        Schema::create('security_withdrawal_addresses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('address_uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('asset', 32)->index();
            $table->string('network', 64)->nullable()->index();
            $table->string('address_hash', 128)->index();
            $table->string('allowlist_state', 32)->default('UNKNOWN')->index();
            $table->unsignedInteger('successful_withdrawals')->default(0);
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->json('risk_flags')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'asset', 'network', 'address_hash'], 'security_withdrawal_address_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_withdrawal_addresses');
        Schema::dropIfExists('security_related_accounts');
        Schema::dropIfExists('security_emergency_controls');
        Schema::dropIfExists('security_rules');
        Schema::dropIfExists('security_incidents');
        Schema::dropIfExists('security_cases');
        Schema::dropIfExists('security_risk_decisions');
        Schema::dropIfExists('security_risk_signals');
    }
};
