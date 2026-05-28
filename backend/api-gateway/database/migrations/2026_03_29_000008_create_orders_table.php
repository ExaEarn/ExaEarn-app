<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->string('pair', 32)->index();
            $table->string('side', 8)->index();
            $table->string('type', 16)->index();
            $table->decimal('price', 24, 8)->default(0);
            $table->decimal('amount', 24, 8);
            $table->decimal('filled_amount', 24, 8)->default(0);
            $table->decimal('remaining_amount', 24, 8);
            $table->decimal('locked_amount', 24, 8)->default(0);
            $table->string('locked_currency', 16)->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['market_id', 'side', 'status', 'price', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

