<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('giftcard_rates', function (Blueprint $table): void {
            if (!Schema::hasColumn('giftcard_rates', 'card_type')) {
                $table->string('card_type', 120)->nullable()->after('brand');
            }

            if (!Schema::hasColumn('giftcard_rates', 'rate_to_usdt')) {
                $table->decimal('rate_to_usdt', 18, 18)->nullable()->after('rate');
            }

            if (!Schema::hasColumn('giftcard_rates', 'source')) {
                $table->string('source', 50)->nullable()->after('active');
            }

            if (!Schema::hasColumn('giftcard_rates', 'last_updated')) {
                $table->timestamp('last_updated')->nullable()->after('source');
            }
        });

        DB::table('giftcard_rates')
            ->whereNull('card_type')
            ->orWhere('card_type', '')
            ->update(['card_type' => DB::raw('UPPER(brand)')]);

        DB::table('giftcard_rates')
            ->whereNull('rate_to_usdt')
            ->update(['rate_to_usdt' => DB::raw('rate')]);

        DB::table('giftcard_rates')
            ->whereNull('source')
            ->update(['source' => 'internal']);

        DB::table('giftcard_rates')
            ->whereNull('last_updated')
            ->update(['last_updated' => now()]);
    }

    public function down(): void
    {
        Schema::table('giftcard_rates', function (Blueprint $table): void {
            if (Schema::hasColumn('giftcard_rates', 'last_updated')) {
                $table->dropColumn('last_updated');
            }

            if (Schema::hasColumn('giftcard_rates', 'source')) {
                $table->dropColumn('source');
            }

            if (Schema::hasColumn('giftcard_rates', 'rate_to_usdt')) {
                $table->dropColumn('rate_to_usdt');
            }

            if (Schema::hasColumn('giftcard_rates', 'card_type')) {
                $table->dropColumn('card_type');
            }
        });
    }
};
