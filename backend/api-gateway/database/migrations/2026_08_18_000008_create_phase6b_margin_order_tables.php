<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('margin_orders')) {
            Schema::create('margin_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('margin_order_uuid')->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('margin_account_id')->constrained('margin_accounts')->cascadeOnDelete();
                $table->foreignId('spot_order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->string('client_order_id')->nullable();
                $table->string('pair', 32);
                $table->string('side', 12);
                $table->string('type', 24);
                $table->string('borrow_mode', 24)->default('NORMAL');
                $table->string('auto_borrow_asset', 16)->nullable();
                $table->decimal('auto_borrow_amount', 36, 18)->default('0');
                $table->string('auto_borrow_reference')->nullable();
                $table->string('auto_repay_asset', 16)->nullable();
                $table->decimal('auto_repay_amount', 36, 18)->default('0');
                $table->decimal('amount', 36, 18);
                $table->decimal('price', 36, 18)->nullable();
                $table->string('status', 32)->default('PENDING');
                $table->json('risk_snapshot')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'client_order_id'], 'margin_orders_user_client_unique');
                $table->index(['user_id', 'status']);
                $table->index(['margin_account_id', 'status']);
                $table->index(['pair', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('margin_orders');
    }
};
