<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('email')->comment('Phone number in E.164 format');
            }
            if (!Schema::hasColumn('users', 'phone_country_code')) {
                $table->string('phone_country_code', 3)->nullable()->after('phone')->comment('ISO 3166-1 alpha-3 country code');
            }
            if (!Schema::hasColumn('users', 'phone_normalized')) {
                $table->string('phone_normalized', 20)->nullable()->unique()->after('phone_country_code')->comment('Normalized phone for duplicate detection');
            }
            // phone_verified_at already exists in previous migration
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['phone_normalized', 'phone_country_code', 'phone'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropUnique(['phone_normalized']);
                    $table->dropColumn($column);
                }
            }
        });
    }
};
