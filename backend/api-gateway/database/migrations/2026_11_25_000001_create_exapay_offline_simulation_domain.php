<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exapay_offline_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_uuid');
            $table->text('public_key');
            $table->char('public_key_fingerprint', 64)->unique();
            $table->string('status', 24)->default('active')->index();
            $table->unsignedSmallInteger('protocol_version')->default(1);
            $table->unsignedBigInteger('device_counter')->default(0);
            $table->unsignedBigInteger('journal_counter')->default(0);
            $table->char('last_journal_digest', 64)->nullable();
            $table->timestamp('enrolled_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason', 160)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_uuid']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('exapay_offline_device_nonces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_uuid');
            $table->char('nonce_digest', 64)->unique();
            $table->string('purpose', 24);
            $table->timestamp('signed_at');
            $table->timestamps();
            $table->index(['user_id', 'device_uuid', 'purpose']);
        });

        Schema::create('exapay_offline_reserves', function (Blueprint $table): void {
            $table->id();
            $table->string('asset', 16)->unique();
            $table->unsignedBigInteger('reserved_minor')->default(0);
            $table->unsignedBigInteger('outstanding_liability_minor')->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });

        Schema::create('exapay_offline_risk_policies', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('max_amount_minor')->default(10000);
            $table->unsignedBigInteger('max_outstanding_per_user_minor')->default(50000);
            $table->unsignedSmallInteger('max_devices_per_user')->default(3);
            $table->unsignedInteger('instrument_lifetime_seconds')->default(86400);
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exapay_offline_instruments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('instrument_uuid')->unique();
            $table->string('reference', 80)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('exapay_offline_devices')->restrictOnDelete();
            $table->string('asset', 16);
            $table->unsignedBigInteger('amount_minor');
            $table->string('idempotency_key', 160);
            $table->char('request_hash', 64);
            $table->unsignedBigInteger('device_counter');
            $table->unsignedSmallInteger('protocol_version');
            $table->string('nonce', 128);
            $table->json('payload');
            $table->text('issuer_signature');
            $table->text('issuer_public_key');
            $table->string('status', 32)->default('pending_acknowledgment')->index();
            $table->string('delivery_status', 24)->default('pending')->index();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->unique(['device_id', 'device_counter']);
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['device_id', 'status']);
        });

        Schema::create('exapay_offline_journal_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('journal_uuid')->unique();
            $table->foreignId('device_id')->constrained('exapay_offline_devices')->restrictOnDelete();
            $table->unsignedBigInteger('device_counter');
            $table->char('previous_digest', 64)->nullable();
            $table->char('event_digest', 64);
            $table->char('nonce_digest', 64);
            $table->json('payload');
            $table->text('device_signature');
            $table->string('status', 24)->default('pending_review')->index();
            $table->string('conflict_code', 48)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['device_id', 'device_counter']);
            $table->unique(['device_id', 'nonce_digest']);
            $table->unique('event_digest');
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('exapay_offline_consumption_claims', function (Blueprint $table): void {
            $table->id();
            $table->uuid('claim_uuid')->unique();
            $table->foreignId('instrument_id')->constrained('exapay_offline_instruments')->restrictOnDelete();
            $table->foreignId('journal_submission_id')->constrained('exapay_offline_journal_submissions')->restrictOnDelete();
            $table->foreignId('device_id')->constrained('exapay_offline_devices')->restrictOnDelete();
            $table->timestamp('claimed_consumed_at');
            $table->string('status', 24)->default('claimed_pending_reconciliation')->index();
            $table->timestamps();
            $table->unique('instrument_id');
            $table->unique(['device_id', 'claim_uuid']);
        });

        DB::table('exapay_offline_reserves')->insert([
            'asset' => 'SIMUSDT', 'reserved_minor' => 0, 'outstanding_liability_minor' => 0, 'version' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('exapay_offline_risk_policies')->insert([
            'enabled' => false,
            'max_amount_minor' => (int) config('exapay_offline.defaults.max_amount_minor', 10000),
            'max_outstanding_per_user_minor' => (int) config('exapay_offline.defaults.max_outstanding_per_user_minor', 50000),
            'max_devices_per_user' => (int) config('exapay_offline.defaults.max_devices_per_user', 3),
            'instrument_lifetime_seconds' => (int) config('exapay_offline.defaults.instrument_lifetime_seconds', 86400),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if (Schema::hasTable('permissions')) {
            foreach (['exapay.offline.view', 'exapay.offline.manage'] as $permission) {
                DB::table('permissions')->insertOrIgnore(['name' => $permission, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        if (Schema::hasTable('accounts') && !DB::table('accounts')->whereNull('user_id')->where('account_type', 'exapay_offline_reserve')->where('asset', 'SIMUSDT')->exists()) {
            DB::table('accounts')->insert([
                'owner_type' => 'system', 'owner_id' => null, 'user_id' => null,
                'account_type' => 'exapay_offline_reserve', 'asset' => 'SIMUSDT', 'balance' => '0',
                'status' => 'active', 'metadata' => json_encode(['purpose' => 'simulation_only', 'real_funds' => false], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('exapay_offline_instruments') && DB::table('exapay_offline_instruments')->exists()) {
            throw new \RuntimeException('ExaPay offline issuance history exists; archive and reconcile it before rolling back this migration.');
        }
        if (Schema::hasTable('exapay_offline_journal_submissions') && DB::table('exapay_offline_journal_submissions')->exists()) {
            throw new \RuntimeException('ExaPay offline journals exist; archive and reconcile them before rolling back this migration.');
        }
        Schema::dropIfExists('exapay_offline_consumption_claims');
        Schema::dropIfExists('exapay_offline_journal_submissions');
        Schema::dropIfExists('exapay_offline_instruments');
        Schema::dropIfExists('exapay_offline_risk_policies');
        Schema::dropIfExists('exapay_offline_reserves');
        Schema::dropIfExists('exapay_offline_device_nonces');
        Schema::dropIfExists('exapay_offline_devices');
        if (Schema::hasTable('accounts')) {
            $reserve = DB::table('accounts')->whereNull('user_id')->where('account_type', 'exapay_offline_reserve')->where('asset', 'SIMUSDT')->first();
            if ($reserve && (string) $reserve->balance === '0.000000000000000000'
                && !DB::table('ledger_entries')->where('account_id', $reserve->id)->exists()) {
                DB::table('accounts')->where('id', $reserve->id)->delete();
            }
        }
    }
};
