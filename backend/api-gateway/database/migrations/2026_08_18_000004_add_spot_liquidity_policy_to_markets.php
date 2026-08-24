<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markets', function (Blueprint $table): void {
            if (!Schema::hasColumn('markets', 'liquidity_mode')) {
                $table->string('liquidity_mode', 32)->default('INTERNAL_ONLY')->after('engine_mode')->index();
            }

            if (!Schema::hasColumn('markets', 'price_authority_mode')) {
                $table->string('price_authority_mode', 32)->default('REFERENCE_ASSISTED')->after('liquidity_mode')->index();
            }

            if (!Schema::hasColumn('markets', 'external_routing_enabled')) {
                $table->boolean('external_routing_enabled')->default(false)->after('price_authority_mode')->index();
            }

            if (!Schema::hasColumn('markets', 'external_routing_policy')) {
                $table->json('external_routing_policy')->nullable()->after('external_routing_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table): void {
            foreach (['external_routing_policy', 'external_routing_enabled', 'price_authority_mode', 'liquidity_mode'] as $column) {
                if (Schema::hasColumn('markets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
