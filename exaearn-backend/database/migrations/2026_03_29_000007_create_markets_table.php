<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 32)->unique();
            $table->string('base_currency', 16);
            $table->string('quote_currency', 16);
            $table->string('status', 20)->default('active')->index();
            $table->decimal('last_price', 24, 8)->default(0);
            $table->decimal('price_precision', 24, 8)->default(0.0001);
            $table->decimal('min_order_size', 24, 8)->default(0);
            $table->decimal('max_order_size', 24, 8)->default(0);
            $table->decimal('maker_fee', 10, 8)->default(0);
            $table->decimal('taker_fee', 10, 8)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markets');
    }
};

