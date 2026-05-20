<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->uuid('trade_uuid')->unique();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buy_order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('sell_order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('pair', 32)->index();
            $table->decimal('price', 24, 8);
            $table->decimal('amount', 24, 8);
            $table->decimal('quote_amount', 24, 8);
            $table->decimal('maker_fee', 24, 8)->default(0);
            $table->decimal('taker_fee', 24, 8)->default(0);
            $table->timestamp('executed_at')->useCurrent()->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};

