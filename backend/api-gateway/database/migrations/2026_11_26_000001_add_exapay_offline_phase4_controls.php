<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exapay_offline_consumption_claims', function (Blueprint $table): void {
            $table->string('status', 48)->default('claimed_pending_reconciliation')->change();
        });

        Schema::table('exapay_offline_risk_policies', function (Blueprint $table): void {
            $table->unsignedBigInteger('max_device_exposure_minor')->default(10000);
            $table->unsignedBigInteger('max_incoming_exposure_per_user_minor')->default(50000);
            $table->unsignedInteger('max_transactions_per_device')->default(100);
            $table->unsignedInteger('max_journal_bytes')->default(262144);
            $table->json('supported_assets')->default('["SIMUSDT"]');
            $table->json('allowed_device_classes')->default('["SIMULATOR_ONLY","SOFTWARE_ONLY"]');
            $table->unsignedBigInteger('trust_epoch')->default(1);
            $table->boolean('require_enrolled_device')->default(true);
        });

        Schema::table('exapay_offline_devices', function (Blueprint $table): void {
            $table->string('trust_class', 32)->default('SOFTWARE_ONLY')->index();
            $table->string('attestation_status', 24)->default('NOT_VERIFIED')->index();
            $table->string('attestation_format', 48)->nullable();
            $table->char('attestation_digest', 64)->nullable();
            $table->unsignedBigInteger('trust_epoch')->default(1);
            $table->unsignedBigInteger('offline_spending_allowance_minor')->default(0);
            $table->unsignedBigInteger('cumulative_spent_minor')->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->timestamp('last_counter_accepted_at')->nullable();
        });
        DB::table('exapay_offline_devices')->update([
            'offline_spending_allowance_minor' => DB::raw('(SELECT max_device_exposure_minor FROM exapay_offline_risk_policies WHERE id = 1)'),
        ]);

        Schema::create('exapay_offline_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('policy_version')->unique();
            $table->char('policy_digest', 64)->unique();
            $table->longText('canonical_payload');
            $table->text('policy_signature');
            $table->text('issuer_public_key');
            $table->string('issuer_key_id', 64);
            $table->string('status', 16)->default('ACTIVE')->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('created_at');
            $table->index(['status', 'policy_version']);
        });

        Schema::create('exapay_offline_security_controls', function (Blueprint $table): void {
            $table->unsignedSmallInteger('id')->primary();
            $table->boolean('issuance_suspended')->default(false);
            $table->boolean('redemption_suspended')->default(false);
            $table->unsignedBigInteger('trust_epoch')->default(1);
            $table->json('blocked_device_families')->default('[]');
            $table->string('suspension_reason', 160)->nullable();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exapay_offline_issuer_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('key_id', 64)->unique();
            $table->text('public_key');
            $table->char('fingerprint', 64)->unique();
            $table->string('status', 16)->default('ACTIVE')->index();
            $table->timestamp('activated_at');
            $table->timestamp('compromised_at')->nullable();
            $table->string('compromise_reason', 160)->nullable();
            $table->foreignId('recorded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exapay_offline_fraud_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('case_uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('exapay_offline_devices')->restrictOnDelete();
            $table->string('reason_code', 64)->index();
            $table->string('severity', 16)->index();
            $table->string('status', 24)->default('OPEN')->index();
            $table->json('evidence');
            $table->timestamp('detected_at');
            $table->foreignId('investigated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('investigated_at')->nullable();
            $table->string('resolution_code', 64)->nullable();
            $table->timestamps();
            $table->index(['status', 'severity', 'detected_at']);
        });

        Schema::create('exapay_offline_admin_change_approvals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('approval_uuid')->unique();
            $table->string('change_type', 48);
            $table->json('proposed_change');
            $table->char('change_digest', 64);
            $table->string('idempotency_key', 160)->nullable();
            $table->foreignId('requested_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->string('status', 16)->default('PENDING')->index();
            $table->timestamp('expires_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['change_type', 'status', 'created_at']);
            $table->unique(['requested_by_admin_id', 'idempotency_key'], 'exapay_offline_admin_change_idem');
        });

        DB::table('exapay_offline_security_controls')->insertOrIgnore([
            'id' => 1, 'issuance_suspended' => false, 'redemption_suspended' => false, 'trust_epoch' => 1,
            'blocked_device_families' => json_encode([], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('exapay_offline_consumption_claims')
            && DB::table('exapay_offline_consumption_claims')->whereRaw('LENGTH(status) > 24')->exists()) {
            throw new RuntimeException('Long offline consumption claim states exist; archive them before narrowing the status column.');
        }
        foreach (['exapay_offline_policy_versions', 'exapay_offline_issuer_keys', 'exapay_offline_fraud_cases', 'exapay_offline_admin_change_approvals'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Phase 4 security history exists; archive it before rolling back this migration.');
            }
        }
        Schema::dropIfExists('exapay_offline_admin_change_approvals');
        Schema::dropIfExists('exapay_offline_fraud_cases');
        Schema::dropIfExists('exapay_offline_issuer_keys');
        Schema::dropIfExists('exapay_offline_security_controls');
        Schema::dropIfExists('exapay_offline_policy_versions');
        Schema::table('exapay_offline_consumption_claims', function (Blueprint $table): void {
            $table->string('status', 24)->default('claimed_pending_reconciliation')->change();
        });
        Schema::table('exapay_offline_devices', function (Blueprint $table): void {
            $table->dropColumn(['trust_class', 'attestation_status', 'attestation_format', 'attestation_digest', 'trust_epoch', 'offline_spending_allowance_minor', 'cumulative_spent_minor', 'transaction_count', 'last_counter_accepted_at']);
        });
        Schema::table('exapay_offline_risk_policies', function (Blueprint $table): void {
            $table->dropColumn(['max_device_exposure_minor', 'max_incoming_exposure_per_user_minor', 'max_transactions_per_device', 'max_journal_bytes', 'supported_assets', 'allowed_device_classes', 'trust_epoch', 'require_enrolled_device']);
        });
    }
};
