<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_trades', function (Blueprint $table): void {
            if (!Schema::hasColumn('p2p_trades', 'escrow_reservation_id')) {
                $table->uuid('escrow_reservation_id')->nullable()->after('escrow_transaction_id')->index();
            }
            if (!Schema::hasColumn('p2p_trades', 'escrow_ledger_reference')) {
                $table->string('escrow_ledger_reference', 180)->nullable()->after('escrow_reservation_id')->index();
            }
            if (!Schema::hasColumn('p2p_trades', 'release_ledger_transaction_id')) {
                $table->foreignId('release_ledger_transaction_id')->nullable()->after('release_transaction_id')->constrained('ledger_transactions')->nullOnDelete();
            }
            if (!Schema::hasColumn('p2p_trades', 'release_ledger_reference')) {
                $table->string('release_ledger_reference', 180)->nullable()->after('release_ledger_transaction_id')->unique();
            }
            if (!Schema::hasColumn('p2p_trades', 'return_ledger_reference')) {
                $table->string('return_ledger_reference', 180)->nullable()->after('return_transaction_id')->unique();
            }
            if (!Schema::hasColumn('p2p_trades', 'buyer_marked_paid_at')) {
                $table->timestamp('buyer_marked_paid_at')->nullable()->after('payment_sent_at');
            }
            if (!Schema::hasColumn('p2p_trades', 'seller_release_due_at')) {
                $table->timestamp('seller_release_due_at')->nullable()->after('buyer_marked_paid_at')->index();
            }
            if (!Schema::hasColumn('p2p_trades', 'dispute_window_ends_at')) {
                $table->timestamp('dispute_window_ends_at')->nullable()->after('seller_release_due_at')->index();
            }
        });

        if (!Schema::hasTable('p2p_assets')) {
            Schema::create('p2p_assets', function (Blueprint $table): void {
                $table->id();
                $table->string('asset', 16)->unique();
                $table->string('status', 24)->default('enabled')->index();
                $table->decimal('min_order_amount', 36, 18)->default('0');
                $table->decimal('max_order_amount', 36, 18)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('p2p_merchant_profiles')) {
            Schema::create('p2p_merchant_profiles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('merchant_uuid')->unique();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('display_name', 120);
                $table->string('state', 32)->default('applied')->index();
                $table->string('tier', 32)->default('standard')->index();
                $table->decimal('reputation_score', 10, 4)->default('0');
                $table->unsignedBigInteger('completed_orders')->default(0);
                $table->decimal('completion_rate', 10, 4)->default('0');
                $table->decimal('dispute_rate', 10, 4)->default('0');
                $table->decimal('volume_30d', 36, 18)->default('0');
                $table->json('supported_fiat_currencies')->nullable();
                $table->json('supported_payment_methods')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('restricted_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('p2p_order_events')) {
            Schema::create('p2p_order_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('trade_id')->constrained('p2p_trades')->cascadeOnDelete();
                $table->uuid('event_id')->unique();
                $table->string('event_type', 80)->index();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('correlation_id', 160)->nullable()->index();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index(['trade_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('p2p_escrows')) {
            Schema::create('p2p_escrows', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('trade_id')->unique()->constrained('p2p_trades')->cascadeOnDelete();
                $table->uuid('escrow_id')->unique();
                $table->uuid('reservation_id')->unique();
                $table->string('asset', 16);
                $table->decimal('amount', 36, 18);
                $table->string('status', 32)->default('reserved')->index();
                $table->string('release_reference', 180)->nullable()->unique();
                $table->string('return_reference', 180)->nullable()->unique();
                $table->json('metadata')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('p2p_payment_evidence')) {
            Schema::create('p2p_payment_evidence', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('trade_id')->constrained('p2p_trades')->cascadeOnDelete();
                $table->uuid('evidence_id')->unique();
                $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
                $table->string('evidence_type', 40)->default('payment_proof');
                $table->string('storage_path')->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->string('sha256', 64)->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('p2p_reputation_snapshots')) {
            Schema::create('p2p_reputation_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('score_version', 32);
                $table->decimal('score', 10, 4);
                $table->json('factors');
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('p2p_risk_events')) {
            Schema::create('p2p_risk_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('risk_event_id')->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('trade_id')->nullable()->constrained('p2p_trades')->nullOnDelete();
                $table->string('decision', 40)->index();
                $table->string('severity', 24)->default('low')->index();
                $table->json('signals')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('p2p_reconciliation_runs')) {
            Schema::create('p2p_reconciliation_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('run_id')->unique();
                $table->string('status', 32)->default('completed')->index();
                $table->decimal('active_escrow_total', 36, 18)->default('0');
                $table->unsignedInteger('difference_count')->default(0);
                $table->json('findings')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_reconciliation_runs');
        Schema::dropIfExists('p2p_risk_events');
        Schema::dropIfExists('p2p_reputation_snapshots');
        Schema::dropIfExists('p2p_payment_evidence');
        Schema::dropIfExists('p2p_escrows');
        Schema::dropIfExists('p2p_order_events');
        Schema::dropIfExists('p2p_merchant_profiles');
        Schema::dropIfExists('p2p_assets');

        Schema::table('p2p_trades', function (Blueprint $table): void {
            foreach ([
                'dispute_window_ends_at',
                'seller_release_due_at',
                'buyer_marked_paid_at',
                'return_ledger_reference',
                'release_ledger_reference',
                'release_ledger_transaction_id',
                'escrow_ledger_reference',
                'escrow_reservation_id',
            ] as $column) {
                if (Schema::hasColumn('p2p_trades', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
