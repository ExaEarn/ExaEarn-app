<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('giftcard_submissions', function (Blueprint $table): void {
            $table->string('card_hash', 64)->after('currency');
            $table->index('card_hash');
        });
    }

    public function down(): void
    {
        Schema::table('giftcard_submissions', function (Blueprint $table): void {
            $table->dropIndex(['card_hash']);
            $table->dropColumn('card_hash');
        });
    }
};
