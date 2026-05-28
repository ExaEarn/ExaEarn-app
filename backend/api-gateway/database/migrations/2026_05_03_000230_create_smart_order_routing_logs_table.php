<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('smart_order_routing_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('symbol', 40);
            $table->string('side', 10);
            $table->decimal('requested_quantity', 20, 8);
            $table->decimal('executed_quantity', 20, 8)->default(0);
            $table->decimal('avg_execution_price', 20, 8)->nullable();
            $table->decimal('expected_best_price', 20, 8)->nullable();
            $table->decimal('slippage_percent', 12, 6)->nullable();
            $table->unsignedInteger('execution_time_ms')->default(0);
            $table->json('route_plan')->nullable();
            $table->json('execution_result')->nullable();
            $table->string('status', 20)->default('success');
            $table->timestamps();

            $table->index(['symbol', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_order_routing_logs');
    }
};
