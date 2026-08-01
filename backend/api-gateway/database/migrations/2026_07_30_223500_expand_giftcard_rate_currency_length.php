<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasTable('giftcard_rates') && Schema::hasColumn('giftcard_rates', 'currency')) {
            DB::statement("ALTER TABLE giftcard_rates ALTER COLUMN currency TYPE VARCHAR(16)");
            DB::statement("ALTER TABLE giftcard_rates ALTER COLUMN currency SET DEFAULT 'USD'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasTable('giftcard_rates') && Schema::hasColumn('giftcard_rates', 'currency')) {
            DB::statement("UPDATE giftcard_rates SET currency = LEFT(currency, 3) WHERE LENGTH(currency) > 3");
            DB::statement("ALTER TABLE giftcard_rates ALTER COLUMN currency TYPE VARCHAR(3)");
            DB::statement("ALTER TABLE giftcard_rates ALTER COLUMN currency SET DEFAULT 'USD'");
        }
    }
};
