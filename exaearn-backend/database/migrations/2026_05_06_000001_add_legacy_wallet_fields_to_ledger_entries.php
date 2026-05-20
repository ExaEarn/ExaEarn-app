<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table): void {
            if (!Schema::hasColumn('ledger_entries', 'wallet_type')) {
                $table->string('wallet_type', 32)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('ledger_entries', 'type')) {
                $table->string('type', 32)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('ledger_entries', 'reference_id')) {
                $table->string('reference_id', 100)->nullable()->after('reference');
            }
            if (!Schema::hasColumn('ledger_entries', 'balance_before')) {
                $table->decimal('balance_before', 36, 18)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('ledger_entries', 'status')) {
                $table->string('status', 20)->default('completed')->after('transaction_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table): void {
            foreach (['wallet_type', 'type', 'reference_id', 'balance_before', 'status'] as $column) {
                if (Schema::hasColumn('ledger_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
