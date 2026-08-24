<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_chart_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('account_code', 32)->unique();
            $table->string('name', 160);
            $table->string('category', 40)->index();
            $table->string('parent_code', 32)->nullable()->index();
            $table->string('ownership_class', 64)->nullable()->index();
            $table->string('normal_balance', 16)->default('DEBIT');
            $table->string('status', 32)->default('ACTIVE')->index();
            $table->string('policy_version', 80)->default('phase17-v1');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_account_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('mapping_uuid')->unique();
            $table->string('source_type', 64)->index();
            $table->string('source_key', 160)->index();
            $table->foreignId('finance_chart_account_id')->constrained('finance_chart_accounts')->cascadeOnDelete();
            $table->string('ownership_class', 64)->index();
            $table->string('status', 32)->default('ACTIVE')->index();
            $table->string('rule_version', 80)->default('phase17-v1');
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_key', 'status'], 'finance_mapping_source_status_unique');
        });

        Schema::create('finance_financial_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_uuid')->unique();
            $table->string('event_type', 96)->index();
            $table->string('source_service', 96)->index();
            $table->string('source_reference', 180)->index();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->nullOnDelete();
            $table->string('asset', 32)->nullable()->index();
            $table->decimal('amount', 36, 18)->nullable();
            $table->string('status', 40)->default('POSTED')->index();
            $table->string('idempotency_key', 180)->unique();
            $table->timestamp('economic_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['event_type', 'source_reference'], 'finance_event_type_source_unique');
        });

        Schema::create('finance_journals', function (Blueprint $table): void {
            $table->id();
            $table->string('journal_uuid')->unique();
            $table->string('journal_number')->unique();
            $table->foreignId('financial_event_id')->constrained('finance_financial_events')->cascadeOnDelete();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->string('description', 240);
            $table->date('transaction_date')->index();
            $table->date('posting_date')->index();
            $table->string('status', 32)->default('POSTED')->index();
            $table->string('reporting_currency', 16)->default('USD');
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finance_journal_id')->constrained('finance_journals')->cascadeOnDelete();
            $table->foreignId('finance_chart_account_id')->constrained('finance_chart_accounts')->restrictOnDelete();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->foreignId('ledger_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('asset', 32)->index();
            $table->decimal('debit', 36, 18)->default(0);
            $table->decimal('credit', 36, 18)->default(0);
            $table->string('reporting_currency', 16)->default('USD');
            $table->decimal('reporting_value', 36, 18)->nullable();
            $table->decimal('valuation_rate', 36, 18)->nullable();
            $table->timestamp('valuation_at')->nullable();
            $table->string('valuation_source', 96)->nullable();
            $table->string('ownership_class', 64)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['finance_journal_id', 'asset']);
        });

        Schema::create('finance_valuation_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('valuation_uuid')->unique();
            $table->string('asset', 32)->index();
            $table->string('reporting_currency', 16)->index();
            $table->decimal('rate', 36, 18);
            $table->string('source', 96);
            $table->string('quality', 32)->default('VERIFIED');
            $table->timestamp('valued_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['asset', 'reporting_currency', 'valued_at'], 'finance_valuation_asset_currency_time');
        });

        Schema::create('finance_asset_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('source_uuid')->unique();
            $table->string('source_type', 64)->index();
            $table->string('source_reference', 180)->index();
            $table->string('asset', 32)->index();
            $table->decimal('amount', 36, 18);
            $table->string('location', 160)->nullable();
            $table->string('ownership_class', 64)->index();
            $table->boolean('eligible_for_backing')->default(false)->index();
            $table->boolean('restricted')->default(false)->index();
            $table->string('freshness', 32)->default('UNVERIFIED')->index();
            $table->string('status', 32)->default('ACTIVE')->index();
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_reference', 'asset'], 'finance_asset_source_unique');
        });

        Schema::create('finance_backing_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('snapshot_uuid')->unique();
            $table->string('asset', 32)->index();
            $table->decimal('liability', 36, 18)->default(0);
            $table->decimal('gross_assets', 36, 18)->default(0);
            $table->decimal('restricted_assets', 36, 18)->default(0);
            $table->decimal('eligible_backing', 36, 18)->default(0);
            $table->decimal('surplus_deficit', 36, 18)->default(0);
            $table->decimal('coverage_ratio', 36, 18)->nullable();
            $table->string('status', 32)->default('UNKNOWN')->index();
            $table->string('freshness', 32)->default('UNVERIFIED');
            $table->timestamp('calculated_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_reconciliation_breaks', function (Blueprint $table): void {
            $table->id();
            $table->string('break_uuid')->unique();
            $table->string('scope', 64)->index();
            $table->string('severity', 32)->index();
            $table->string('code', 120)->index();
            $table->string('status', 32)->default('OPEN')->index();
            $table->string('subject_type', 96)->nullable();
            $table->string('subject_reference', 180)->nullable();
            $table->text('message');
            $table->json('evidence')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_adjustment_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('adjustment_uuid')->unique();
            $table->foreignId('requested_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->string('asset', 32)->index();
            $table->decimal('amount', 36, 18);
            $table->string('debit_account_type', 120);
            $table->string('credit_account_type', 120);
            $table->string('reason_code', 120);
            $table->text('reason');
            $table->string('status', 32)->default('PENDING_APPROVAL')->index();
            $table->string('ledger_reference', 180)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_close_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('close_uuid')->unique();
            $table->string('period_type', 24)->index();
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->string('status', 32)->default('PREPARED')->index();
            $table->string('reporting_currency', 16)->default('USD');
            $table->foreignId('prepared_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->unique(['period_type', 'period_start', 'period_end'], 'finance_close_period_unique');
        });

        Schema::create('finance_report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('report_uuid')->unique();
            $table->string('report_type', 80)->index();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('reporting_currency', 16)->default('USD');
            $table->string('status', 32)->default('GENERATED')->index();
            $table->string('version', 80)->default('phase17-v1');
            $table->timestamp('valuation_at')->nullable();
            $table->string('valuation_source', 96)->nullable();
            $table->json('payload');
            $table->foreignId('generated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('generated_at')->index();
            $table->timestamps();
        });

        Schema::create('finance_dead_letter_events', function (Blueprint $table): void {
            $table->id();
            $table->string('dlq_uuid')->unique();
            $table->string('event_type', 96)->index();
            $table->string('source_service', 96)->nullable();
            $table->string('source_reference', 180)->nullable();
            $table->string('status', 32)->default('OPEN')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message');
            $table->json('payload')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_dead_letter_events');
        Schema::dropIfExists('finance_report_snapshots');
        Schema::dropIfExists('finance_close_periods');
        Schema::dropIfExists('finance_adjustment_requests');
        Schema::dropIfExists('finance_reconciliation_breaks');
        Schema::dropIfExists('finance_backing_snapshots');
        Schema::dropIfExists('finance_asset_sources');
        Schema::dropIfExists('finance_valuation_snapshots');
        Schema::dropIfExists('finance_journal_lines');
        Schema::dropIfExists('finance_journals');
        Schema::dropIfExists('finance_financial_events');
        Schema::dropIfExists('finance_account_mappings');
        Schema::dropIfExists('finance_chart_accounts');
    }
};
