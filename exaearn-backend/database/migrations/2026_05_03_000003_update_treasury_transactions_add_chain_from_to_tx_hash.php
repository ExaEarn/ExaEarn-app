<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table): void {
            if (!Schema::hasColumn('treasury_transactions', 'chain')) {
                $table->string('chain', 32)->nullable()->after('type');
            }

            if (!Schema::hasColumn('treasury_transactions', 'from_address')) {
                $table->string('from_address', 128)->nullable()->after('currency');
            }

            if (!Schema::hasColumn('treasury_transactions', 'to_address')) {
                $table->string('to_address', 128)->nullable()->after('from_address');
            }

            if (!Schema::hasColumn('treasury_transactions', 'tx_hash')) {
                $table->string('tx_hash', 128)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('treasury_transactions', 'tx_hash')) {
                $table->dropColumn('tx_hash');
            }
            if (Schema::hasColumn('treasury_transactions', 'to_address')) {
                $table->dropColumn('to_address');
            }
            if (Schema::hasColumn('treasury_transactions', 'from_address')) {
                $table->dropColumn('from_address');
            }
            if (Schema::hasColumn('treasury_transactions', 'chain')) {
                $table->dropColumn('chain');
            }
        });
    }
};
