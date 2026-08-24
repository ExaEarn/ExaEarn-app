<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            if (!Schema::hasColumn('accounts', 'owner_type')) {
                $table->string('owner_type', 40)->default('user')->after('id')->index();
            }
            if (!Schema::hasColumn('accounts', 'owner_id')) {
                $table->unsignedBigInteger('owner_id')->nullable()->after('owner_type')->index();
            }
            if (!Schema::hasColumn('accounts', 'status')) {
                $table->string('status', 24)->default('active')->after('balance')->index();
            }
            if (!Schema::hasColumn('accounts', 'metadata')) {
                $table->json('metadata')->nullable()->after('status');
            }
        });

        Schema::table('ledger_transactions', function (Blueprint $table): void {
            if (!Schema::hasColumn('ledger_transactions', 'transaction_type')) {
                $table->string('transaction_type', 64)->nullable()->after('description')->index();
            }
            if (!Schema::hasColumn('ledger_transactions', 'source_service')) {
                $table->string('source_service', 80)->nullable()->after('transaction_type')->index();
            }
            if (!Schema::hasColumn('ledger_transactions', 'initiated_by_type')) {
                $table->string('initiated_by_type', 40)->nullable()->after('source_service');
            }
            if (!Schema::hasColumn('ledger_transactions', 'initiated_by_id')) {
                $table->unsignedBigInteger('initiated_by_id')->nullable()->after('initiated_by_type')->index();
            }
            if (!Schema::hasColumn('ledger_transactions', 'reversal_of_transaction_id')) {
                $table->foreignId('reversal_of_transaction_id')->nullable()->after('initiated_by_id')->constrained('ledger_transactions')->nullOnDelete();
            }
            if (!Schema::hasColumn('ledger_transactions', 'metadata')) {
                $table->json('metadata')->nullable()->after('status');
            }
        });

        if (!Schema::hasTable('reservations')) {
            Schema::create('reservations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('reservation_id')->unique();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('asset', 16);
                $table->decimal('amount', 36, 18);
                $table->decimal('remaining_amount', 36, 18);
                $table->string('purpose', 64)->index();
                $table->string('reference_type', 80)->nullable()->index();
                $table->string('reference_id', 120)->nullable()->index();
                $table->string('idempotency_key', 160)->nullable()->unique();
                $table->string('status', 32)->default('active')->index();
                $table->json('metadata')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();

                $table->index(['account_id', 'asset', 'status']);
                $table->index(['user_id', 'asset']);
            });
        }

        if (!Schema::hasTable('ledger_reversal_links')) {
            Schema::create('ledger_reversal_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('original_transaction_id')->constrained('ledger_transactions')->cascadeOnDelete();
                $table->foreignId('reversal_transaction_id')->constrained('ledger_transactions')->cascadeOnDelete();
                $table->string('reason', 255);
                $table->string('performed_by_type', 40)->nullable();
                $table->unsignedBigInteger('performed_by_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['original_transaction_id', 'reversal_transaction_id'], 'ledger_reversal_unique_pair');
            });
        }

        if (!Schema::hasTable('financial_operation_idempotencies')) {
            Schema::create('financial_operation_idempotencies', function (Blueprint $table): void {
                $table->id();
                $table->string('idempotency_key', 160)->unique();
                $table->string('operation_type', 80)->index();
                $table->string('reference', 160)->nullable()->index();
                $table->string('status', 32)->default('processing')->index();
                $table->json('request_hash')->nullable();
                $table->json('response_snapshot')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_operation_idempotencies');
        Schema::dropIfExists('ledger_reversal_links');
        Schema::dropIfExists('reservations');

        Schema::table('ledger_transactions', function (Blueprint $table): void {
            foreach (['metadata', 'reversal_of_transaction_id', 'initiated_by_id', 'initiated_by_type', 'source_service', 'transaction_type'] as $column) {
                if (Schema::hasColumn('ledger_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('accounts', function (Blueprint $table): void {
            foreach (['metadata', 'status', 'owner_id', 'owner_type'] as $column) {
                if (Schema::hasColumn('accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
