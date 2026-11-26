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
        Schema::table('exapay_offline_reserves', function (Blueprint $table): void {
            $table->unsignedBigInteger('issued_minor')->default(0);
            $table->unsignedBigInteger('redeemed_minor')->default(0);
            $table->unsignedBigInteger('extinguished_minor')->default(0);
        });
        $issuedMinor = (int) DB::table('exapay_offline_instruments')->sum('amount_minor');
        $outstandingMinor = (int) DB::table('exapay_offline_reserves')->where('asset', 'SIMUSDT')->value('outstanding_liability_minor');
        $redeemedMinor = (int) DB::table('exapay_offline_instruments')->where('status', 'redeemed')->sum('amount_minor');
        if ($issuedMinor < $outstandingMinor + $redeemedMinor) {
            throw new RuntimeException('Historical offline issuance does not cover its outstanding liability; reconcile before Phase 3 migration.');
        }
        DB::table('exapay_offline_reserves')->where('asset', 'SIMUSDT')->update([
            'issued_minor' => $issuedMinor,
            'redeemed_minor' => $redeemedMinor,
            'extinguished_minor' => $issuedMinor - $outstandingMinor - $redeemedMinor,
        ]);

        Schema::create('exapay_offline_reconciliation_claims', function (Blueprint $table): void {
            $table->id();
            $table->uuid('claim_uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 160);
            $table->char('request_digest', 64);
            $table->uuid('expected_output_uuid');
            $table->unsignedBigInteger('expected_amount_minor');
            $table->unsignedInteger('evidence_revision')->default(0);
            $table->unsignedInteger('evaluated_evidence_revision')->default(0);
            $table->string('status', 32)->default('SUBMITTED')->index();
            $table->string('reason_code', 64)->nullable();
            $table->json('result')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'exa_offline_claim_idempotency_unique');
            $table->index(['user_id', 'status', 'created_at'], 'exa_offline_claim_user_status_idx');
            $table->index(['status', 'updated_at'], 'exa_offline_claim_status_updated_idx');
        });

        Schema::create('exapay_offline_reconciliation_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('claim_id')->constrained('exapay_offline_reconciliation_claims')->restrictOnDelete();
            $table->uuid('transfer_uuid');
            $table->char('transfer_digest', 64);
            $table->uuid('input_output_uuid');
            $table->char('parent_digest', 64);
            $table->json('canonical_payload');
            $table->text('sender_signature');
            $table->text('recipient_signature');
            $table->string('status', 24)->default('SUBMITTED')->index();
            $table->timestamps();
            $table->index(['claim_id', 'transfer_uuid'], 'exa_offline_evidence_transfer_idx');
            $table->unique(['claim_id', 'transfer_digest'], 'exa_offline_evidence_digest_unique');
            $table->index(['claim_id', 'input_output_uuid']);
        });

        Schema::create('exapay_offline_reconciliation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('claim_id')->constrained('exapay_offline_reconciliation_claims')->restrictOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32);
            $table->string('event_type', 48);
            $table->char('previous_digest', 64)->nullable();
            $table->string('actor_type', 24)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->char('event_digest', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->unique(['claim_id', 'sequence'], 'exa_offline_claim_event_sequence_unique');
            $table->index(['claim_id', 'created_at']);
        });

        Schema::create('exapay_offline_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('redemption_uuid')->unique();
            $table->foreignId('claim_id')->constrained('exapay_offline_reconciliation_claims')->restrictOnDelete();
            $table->uuid('output_uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('asset', 16)->default('SIMUSDT');
            $table->unsignedBigInteger('amount_minor');
            $table->string('idempotency_key', 160);
            $table->char('request_digest', 64);
            $table->string('ledger_reference', 120)->unique();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('status', 24)->default('REDEMPTION_PENDING')->index();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'exa_offline_redemption_idempotency_unique');
            $table->index(['user_id', 'created_at']);
        });

        // Extending the phase-2 ledger additively preserves the signed transfer wire format.
    }

    public function down(): void
    {
        foreach (['exapay_offline_redemptions', 'exapay_offline_reconciliation_events', 'exapay_offline_reconciliation_evidence', 'exapay_offline_reconciliation_claims'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Offline reconciliation or redemption history exists; archive it before rolling back this migration.');
            }
        }
        Schema::dropIfExists('exapay_offline_redemptions');
        Schema::dropIfExists('exapay_offline_reconciliation_events');
        Schema::dropIfExists('exapay_offline_reconciliation_evidence');
        Schema::dropIfExists('exapay_offline_reconciliation_claims');
        Schema::table('exapay_offline_reserves', function (Blueprint $table): void {
            $table->dropColumn(['issued_minor', 'redeemed_minor', 'extinguished_minor']);
        });
    }
};
