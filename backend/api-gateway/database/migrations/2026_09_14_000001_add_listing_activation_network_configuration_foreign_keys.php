<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FOREIGN_KEYS = [
        'listing_deposit_readiness_checks' => 'listing_deposit_readiness_checks_listing_asset_network_configuration_id_foreign',
        'listing_withdrawal_readiness_checks' => 'listing_withdrawal_readiness_checks_listing_asset_network_configuration_id_foreign',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('listing_asset_network_configurations')) {
            throw new RuntimeException('Listing activation network configuration table must exist before adding its foreign keys.');
        }

        foreach (self::FOREIGN_KEYS as $tableName => $constraintName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'listing_asset_network_configuration_id')) {
                continue;
            }

            $exists = collect(Schema::getForeignKeys($tableName))->contains(
                fn (array $foreignKey): bool => ($foreignKey['name'] ?? null) === $constraintName,
            );
            if ($exists) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($constraintName): void {
                $table->foreign('listing_asset_network_configuration_id', $constraintName)
                    ->references('id')->on('listing_asset_network_configurations')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::FOREIGN_KEYS as $tableName => $constraintName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            $exists = collect(Schema::getForeignKeys($tableName))->contains(
                fn (array $foreignKey): bool => ($foreignKey['name'] ?? null) === $constraintName,
            );
            if ($exists) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign($constraintName));
            }
        }
    }
};
