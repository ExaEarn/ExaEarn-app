<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('stop_price', 24, 8)->nullable()->after('price');
            $table->string('trigger_order_type', 16)->nullable()->after('type');
            $table->timestamp('triggered_at')->nullable()->after('status');

            $table->index(['market_id', 'status', 'stop_price']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['market_id', 'status', 'stop_price']);
            $table->dropColumn(['stop_price', 'trigger_order_type', 'triggered_at']);
        });
    }
};

