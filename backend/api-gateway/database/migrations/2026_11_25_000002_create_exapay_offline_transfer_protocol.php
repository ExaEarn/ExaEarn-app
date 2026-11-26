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
        $duplicateDeviceIds = DB::table('exapay_offline_devices')->select('device_uuid')->groupBy('device_uuid')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicateDeviceIds) throw new RuntimeException('Duplicate device UUIDs exist across users; resolve those collisions before installing offline transfer protocol v1.');
        Schema::table('exapay_offline_devices', function (Blueprint $table): void {
            $table->unique('device_uuid', 'exapay_offline_device_uuid_global_unique');
        });
        Schema::table('exapay_offline_devices', function (Blueprint $table): void {
            $table->unsignedInteger('key_version')->default(1);
            $table->timestamp('key_rotated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
        });

        Schema::create('exapay_offline_device_key_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('exapay_offline_devices')->restrictOnDelete();
            $table->unsignedInteger('key_version');
            $table->text('public_key');
            $table->char('public_key_fingerprint', 64)->unique();
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('valid_from');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'key_version']);
        });
        foreach (DB::table('exapay_offline_devices')->orderBy('id')->get() as $device) {
            DB::table('exapay_offline_device_key_history')->insertOrIgnore([
                'device_id' => $device->id, 'key_version' => 1, 'public_key' => $device->public_key,
                'public_key_fingerprint' => $device->public_key_fingerprint,
                'status' => $device->status === 'active' ? 'active' : 'revoked',
                'valid_from' => $device->enrolled_at, 'revoked_at' => $device->suspended_at,
                'created_at' => $device->created_at, 'updated_at' => now(),
            ]);
        }

        Schema::table('exapay_offline_risk_policies', function (Blueprint $table): void {
            $table->unsignedSmallInteger('max_hops')->default(5);
            $table->unsignedSmallInteger('max_transfers_per_journal')->default(100);
            $table->unsignedBigInteger('max_cumulative_spending_minor')->default(50000);
            $table->unsignedSmallInteger('minimum_protocol_version')->default(1);
            $table->unsignedBigInteger('policy_version')->default(1);
            $table->char('policy_digest', 64)->nullable();
            $table->text('policy_signature')->nullable();
            $table->text('policy_public_key')->nullable();
            $table->timestamp('policy_expires_at')->nullable();
        });

        Schema::create('exapay_offline_value_outputs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('output_uuid')->unique();
            $table->foreignId('source_instrument_id')->nullable()->constrained('exapay_offline_instruments')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('owner_device_id')->constrained('exapay_offline_devices')->restrictOnDelete();
            $table->uuid('parent_output_uuid')->nullable()->index();
            $table->uuid('origin_transfer_uuid')->nullable()->index();
            $table->string('asset', 16)->default('SIMUSDT');
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedSmallInteger('hop_count')->default(0);
            $table->char('lineage_digest', 64);
            $table->string('status', 24)->default('available')->index();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
            $table->index(['owner_user_id', 'owner_device_id', 'status']);
            $table->index(['source_instrument_id', 'status']);
        });

        Schema::create('exapay_offline_transfer_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('transfer_uuid')->unique();
            $table->uuid('claimed_transfer_uuid')->index();
            $table->char('transfer_digest', 64)->unique();
            $table->foreignId('sender_device_id')->constrained('exapay_offline_devices')->restrictOnDelete();
            $table->foreignId('recipient_device_id')->constrained('exapay_offline_devices')->restrictOnDelete();
            $table->unsignedInteger('sender_key_version');
            $table->unsignedInteger('recipient_key_version');
            $table->uuid('input_output_uuid');
            $table->uuid('recipient_output_uuid');
            $table->uuid('change_output_uuid')->nullable();
            $table->string('idempotency_key', 160);
            $table->unsignedBigInteger('input_amount_minor');
            $table->unsignedBigInteger('transfer_amount_minor');
            $table->unsignedBigInteger('change_amount_minor');
            $table->unsignedBigInteger('transfer_counter');
            $table->unsignedSmallInteger('protocol_version');
            $table->unsignedSmallInteger('hop_count');
            $table->char('nonce_digest', 64);
            $table->char('parent_digest', 64)->nullable();
            $table->json('canonical_payload');
            $table->text('sender_signature');
            $table->text('recipient_signature');
            $table->string('status', 24)->default('acknowledged')->index();
            $table->timestamp('prepared_at');
            $table->timestamp('committed_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(['sender_device_id', 'idempotency_key']);
            $table->index(['sender_device_id', 'transfer_counter']);
            $table->index(['recipient_device_id', 'status', 'created_at']);
        });

        Schema::create('exapay_offline_output_spends', function (Blueprint $table): void {
            $table->id();
            $table->uuid('output_uuid')->unique();
            $table->foreignId('transfer_session_id')->constrained('exapay_offline_transfer_sessions')->restrictOnDelete();
            $table->string('status', 24)->default('committed')->index();
            $table->timestamp('spent_at');
            $table->timestamps();
        });

        // Convert every Phase 1 issuance into a root output while preserving its original liability.
        $instruments = DB::table('exapay_offline_instruments as i')
            ->join('exapay_offline_devices as d', 'd.id', '=', 'i.device_id')
            ->select('i.*', 'd.user_id as device_user_id')
            ->orderBy('i.id')->get();
        foreach ($instruments as $instrument) {
            $decoded = is_string($instrument->payload) ? json_decode($instrument->payload, true, 512, JSON_THROW_ON_ERROR) : (array) $instrument->payload;
            $payload = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $digest = hash('sha256', 'exaearn-offline-root:v1|' . $instrument->instrument_uuid . '|' . hash('sha256', $payload));
            DB::table('exapay_offline_value_outputs')->insertOrIgnore([
                'output_uuid' => $instrument->instrument_uuid,
                'source_instrument_id' => $instrument->id,
                'owner_user_id' => $instrument->user_id,
                'owner_device_id' => $instrument->device_id,
                'asset' => 'SIMUSDT',
                'amount_minor' => $instrument->amount_minor,
                'hop_count' => 0,
                'lineage_digest' => $digest,
                'status' => in_array($instrument->status, ['failed', 'redeemed'], true) ? 'spent' : ($instrument->status === 'suspended' ? 'quarantined' : 'available'),
                'created_at' => $instrument->created_at,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('exapay_offline_transfer_sessions') && DB::table('exapay_offline_transfer_sessions')->exists()) {
            throw new RuntimeException('Offline transfer evidence exists; archive it before rolling back this migration.');
        }
        Schema::dropIfExists('exapay_offline_output_spends');
        Schema::dropIfExists('exapay_offline_transfer_sessions');
        Schema::dropIfExists('exapay_offline_value_outputs');
        Schema::dropIfExists('exapay_offline_device_key_history');
        Schema::table('exapay_offline_devices', function (Blueprint $table): void {
            $table->dropColumn(['key_version', 'key_rotated_at', 'revoked_at']);
            $table->dropUnique('exapay_offline_device_uuid_global_unique');
        });
        Schema::table('exapay_offline_risk_policies', function (Blueprint $table): void {
            $table->dropColumn(['max_hops', 'max_transfers_per_journal', 'max_cumulative_spending_minor', 'minimum_protocol_version', 'policy_version', 'policy_digest', 'policy_signature', 'policy_public_key', 'policy_expires_at']);
        });
    }
};
