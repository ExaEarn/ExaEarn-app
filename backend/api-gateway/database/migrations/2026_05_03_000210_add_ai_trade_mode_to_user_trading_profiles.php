<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_trading_profiles', function (Blueprint $table): void {
            $table->string('ai_trade_mode', 20)->default('assist')->after('enable_auto_trading');
        });

        DB::table('user_trading_profiles')
            ->where('enable_auto_trading', true)
            ->update(['ai_trade_mode' => 'auto']);

        DB::table('user_trading_profiles')
            ->where('enable_ai_suggestions', false)
            ->where('enable_auto_trading', false)
            ->update(['ai_trade_mode' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('user_trading_profiles', function (Blueprint $table): void {
            $table->dropColumn('ai_trade_mode');
        });
    }
};
