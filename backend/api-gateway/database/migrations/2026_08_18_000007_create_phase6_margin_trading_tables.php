<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('margin_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('account_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 16);
            $table->string('market_symbol', 32)->nullable();
            $table->string('status', 32)->default('ACTIVE');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'mode', 'status']);
            $table->index(['user_id', 'mode', 'market_symbol']);
        });

        Schema::create('margin_asset_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('asset', 24)->unique();
            $table->boolean('borrow_enabled')->default(false);
            $table->boolean('collateral_enabled')->default(false);
            $table->decimal('collateral_factor', 12, 8)->default('0');
            $table->decimal('liquidation_factor', 12, 8)->default('0');
            $table->decimal('borrow_limit', 36, 18)->default('0');
            $table->decimal('minimum_borrow', 36, 18)->default('0');
            $table->decimal('maximum_borrow', 36, 18)->default('0');
            $table->decimal('reserve_factor', 12, 8)->default('0');
            $table->string('interest_model', 32)->default('kinked_utilization');
            $table->decimal('base_rate', 12, 8)->default('0');
            $table->decimal('slope_1', 12, 8)->default('0');
            $table->decimal('optimal_utilization', 12, 8)->default('0.8');
            $table->decimal('slope_2', 12, 8)->default('0');
            $table->decimal('max_rate', 12, 8)->default('0');
            $table->string('status', 32)->default('DISABLED');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('margin_lending_pools', function (Blueprint $table): void {
            $table->id();
            $table->string('asset', 24)->unique();
            $table->decimal('total_liquidity', 36, 18)->default('0');
            $table->decimal('available_liquidity', 36, 18)->default('0');
            $table->decimal('borrowed_liquidity', 36, 18)->default('0');
            $table->decimal('reserve_balance', 36, 18)->default('0');
            $table->string('status', 32)->default('DISABLED');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('margin_loans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('loan_uuid')->unique();
            $table->foreignId('margin_account_id')->constrained('margin_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset', 24);
            $table->decimal('principal', 36, 18);
            $table->decimal('accrued_interest', 36, 18)->default('0');
            $table->decimal('interest_rate', 12, 8);
            $table->timestamp('opened_at');
            $table->timestamp('last_accrual_at');
            $table->string('status', 32)->default('ACTIVE');
            $table->string('idempotency_key')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'asset', 'status']);
            $table->index(['margin_account_id', 'status']);
        });

        Schema::create('margin_interest_accruals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('accrual_id')->unique();
            $table->foreignId('margin_loan_id')->constrained('margin_loans')->cascadeOnDelete();
            $table->string('asset', 24);
            $table->decimal('principal_basis', 36, 18);
            $table->decimal('interest_rate', 12, 8);
            $table->decimal('interest_amount', 36, 18);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['margin_loan_id', 'period_start', 'period_end'], 'margin_interest_period_unique');
            $table->index(['asset', 'period_end']);
        });

        Schema::create('margin_liquidations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('liquidation_id')->unique();
            $table->foreignId('margin_account_id')->constrained('margin_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 16);
            $table->string('market_symbol', 32)->nullable();
            $table->string('status', 32)->default('PENDING');
            $table->decimal('trigger_health_factor', 36, 18)->default('0');
            $table->json('assets_sold')->nullable();
            $table->json('debt_repaid')->nullable();
            $table->decimal('liquidation_fee', 36, 18)->default('0');
            $table->decimal('reserve_impact', 36, 18)->default('0');
            $table->decimal('bad_debt_amount', 36, 18)->default('0');
            $table->string('ledger_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['margin_account_id', 'status']);
        });

        Schema::create('margin_bad_debts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('bad_debt_id')->unique();
            $table->foreignId('margin_account_id')->constrained('margin_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset', 24);
            $table->decimal('amount', 36, 18);
            $table->decimal('covered_amount', 36, 18)->default('0');
            $table->string('status', 32)->default('OPEN');
            $table->string('ledger_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['asset', 'status']);
        });

        Schema::create('margin_reconciliation_findings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('finding_id')->unique();
            $table->string('scope', 64);
            $table->string('severity', 24)->default('INFO');
            $table->string('status', 32)->default('OPEN');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['scope', 'status']);
            $table->index(['severity', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('margin_reconciliation_findings');
        Schema::dropIfExists('margin_bad_debts');
        Schema::dropIfExists('margin_liquidations');
        Schema::dropIfExists('margin_interest_accruals');
        Schema::dropIfExists('margin_loans');
        Schema::dropIfExists('margin_lending_pools');
        Schema::dropIfExists('margin_asset_configs');
        Schema::dropIfExists('margin_accounts');
    }
};
