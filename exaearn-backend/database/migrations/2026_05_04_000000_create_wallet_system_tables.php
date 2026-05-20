<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. User Balances Table
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('asset'); // BTC, USDT, etc.
            
            // Funding Wallet
            $table->decimal('funding_available', 36, 18)->default(0);
            
            // Spot Wallet
            $table->decimal('spot_available', 36, 18)->default(0);
            $table->decimal('spot_locked', 36, 18)->default(0);
            
            // Futures Wallet
            $table->decimal('futures_available', 36, 18)->default(0);
            $table->decimal('futures_margin', 36, 18)->default(0);
            
            $table->unique(['user_id', 'asset']);
            $table->timestamps();
        });

        // 2. Ledger Entries Table
        if (!Schema::hasTable('ledger_entries')) {
            Schema::create('ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained();
                $table->string('wallet_type'); // funding, spot, futures
                $table->string('asset');
                $table->decimal('amount', 36, 18); // positive or negative
                $table->string('type'); // deposit, transfer, trade, withdrawal, lock, unlock, pnl
                $table->string('reference_id'); // e.g., order_id, transfer_id
                $table->decimal('balance_before', 36, 18);
                $table->decimal('balance_after', 36, 18);
                $table->string('status')->default('completed');
                $table->timestamps();
            });
        }

        // 3. Withdrawals Table
        if (!Schema::hasTable('withdrawals')) {
            Schema::create('withdrawals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained();
                $table->string('asset');
                $table->decimal('amount', 36, 18);
                $table->string('address');
                $table->string('status')->default('pending'); // pending, processing, completed, failed
                $table->string('tx_hash')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('balances');
    }
};
