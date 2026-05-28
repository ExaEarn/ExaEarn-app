<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->string('address_type', 32)->nullable()->after('network');
            $table->string('derivation_path', 255)->nullable()->after('address_type');
            $table->unsignedInteger('address_index')->nullable()->after('derivation_path');
            $table->string('status', 20)->default('active')->after('address_index');

            $table->unique(['network', 'address'], 'deposit_addresses_network_address_unique');
            $table->index(['network', 'address_type'], 'deposit_addresses_network_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->dropUnique('deposit_addresses_network_address_unique');
            $table->dropIndex('deposit_addresses_network_type_index');
            $table->dropColumn(['address_type', 'derivation_path', 'address_index', 'status']);
        });
    }
};

