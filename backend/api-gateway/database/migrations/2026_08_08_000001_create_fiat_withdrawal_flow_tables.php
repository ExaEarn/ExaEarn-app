<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fiat_withdrawal_beneficiaries')) {
            Schema::create('fiat_withdrawal_beneficiaries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('country', 2)->default('NG');
                $table->string('currency', 8)->default('NGN');
                $table->string('provider', 32)->default('sandbox');
                $table->string('bank_code', 64);
                $table->string('bank_name', 160);
                $table->string('account_number', 64);
                $table->string('account_name', 160);
                $table->string('masked_account_number', 32);
                $table->boolean('is_default')->default(false);
                $table->string('status', 32)->default('active')->index();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'currency', 'provider']);
                $table->unique(['user_id', 'currency', 'bank_code', 'account_number'], 'fiat_wd_beneficiary_unique');
            });
        }

        if (!Schema::hasTable('fiat_withdrawal_intents')) {
            Schema::create('fiat_withdrawal_intents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('beneficiary_id')->nullable()->constrained('fiat_withdrawal_beneficiaries')->nullOnDelete();
                $table->foreignId('withdrawal_id')->nullable()->constrained('withdrawals')->nullOnDelete();
                $table->string('reference', 80)->unique();
                $table->string('idempotency_key', 160)->nullable();
                $table->string('source_account', 32)->default('funding');
                $table->string('country', 2)->default('NG');
                $table->string('currency', 8)->default('NGN');
                $table->decimal('amount', 36, 18);
                $table->decimal('fee', 36, 18)->default(0);
                $table->decimal('recipient_receives', 36, 18)->default(0);
                $table->decimal('remaining_balance_after', 36, 18)->default(0);
                $table->string('provider', 32)->default('sandbox');
                $table->string('bank_code', 64);
                $table->string('bank_name', 160);
                $table->string('account_number_last4', 8);
                $table->string('account_name', 160);
                $table->string('narration', 255)->default('ExaEarn Withdrawal');
                $table->string('estimated_arrival', 80)->nullable();
                $table->string('status', 40)->default('awaiting_verification')->index();
                $table->string('provider_reference', 120)->nullable()->index();
                $table->string('reserve_ledger_reference', 120)->nullable()->unique();
                $table->string('settlement_ledger_reference', 120)->nullable()->unique();
                $table->string('reversal_ledger_reference', 120)->nullable()->unique();
                $table->timestamp('quote_expires_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'idempotency_key'], 'fiat_wd_intent_idempotency_unique');
                $table->index(['user_id', 'status']);
                $table->index(['currency', 'status']);
            });
        }

        if (!Schema::hasTable('fiat_withdrawal_verification_challenges')) {
            Schema::create('fiat_withdrawal_verification_challenges', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('fiat_withdrawal_intent_id')->constrained('fiat_withdrawal_intents')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('method', 32);
                $table->string('code_hash', 255);
                $table->string('status', 32)->default('pending')->index();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('verified_at')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'method', 'status']);
            });
        }

        if (!Schema::hasTable('fiat_withdrawal_provider_events')) {
            Schema::create('fiat_withdrawal_provider_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('fiat_withdrawal_intent_id')->nullable()->constrained('fiat_withdrawal_intents')->nullOnDelete();
                $table->string('provider', 32);
                $table->string('event_id', 160)->nullable();
                $table->string('event_type', 120);
                $table->string('status', 40)->nullable();
                $table->jsonb('payload');
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'event_id'], 'fiat_wd_provider_event_unique');
                $table->index(['provider', 'event_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fiat_withdrawal_provider_events');
        Schema::dropIfExists('fiat_withdrawal_verification_challenges');
        Schema::dropIfExists('fiat_withdrawal_intents');
        Schema::dropIfExists('fiat_withdrawal_beneficiaries');
    }
};
