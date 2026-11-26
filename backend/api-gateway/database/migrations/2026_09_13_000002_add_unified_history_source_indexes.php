<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_transactions', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at', 'id'], 'card_tx_user_created_id_idx');
        });

        Schema::table('staking_transactions', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at', 'id'], 'staking_tx_user_created_id_idx');
        });

        Schema::table('p2p_trades', function (Blueprint $table): void {
            $table->index(['buyer_id', 'created_at', 'id'], 'p2p_buyer_created_id_idx');
            $table->index(['seller_id', 'created_at', 'id'], 'p2p_seller_created_id_idx');
        });

        Schema::table('giftcard_orders', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at', 'id'], 'giftcard_user_created_id_idx');
        });

        Schema::table('swaps', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at', 'id'], 'swaps_user_created_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('card_transactions', function (Blueprint $table): void {
            $table->dropIndex('card_tx_user_created_id_idx');
        });

        Schema::table('staking_transactions', function (Blueprint $table): void {
            $table->dropIndex('staking_tx_user_created_id_idx');
        });

        Schema::table('p2p_trades', function (Blueprint $table): void {
            $table->dropIndex('p2p_buyer_created_id_idx');
            $table->dropIndex('p2p_seller_created_id_idx');
        });

        Schema::table('giftcard_orders', function (Blueprint $table): void {
            $table->dropIndex('giftcard_user_created_id_idx');
        });

        Schema::table('swaps', function (Blueprint $table): void {
            $table->dropIndex('swaps_user_created_id_idx');
        });
    }
};
