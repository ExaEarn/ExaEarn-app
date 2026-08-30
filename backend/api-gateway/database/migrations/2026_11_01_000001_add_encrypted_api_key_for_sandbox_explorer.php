<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developer_api_keys', function (Blueprint $table): void {
            $table->text('encrypted_api_key')->nullable()->after('key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('developer_api_keys', function (Blueprint $table): void {
            $table->dropColumn('encrypted_api_key');
        });
    }
};
