<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('account_type', 32);
            $table->string('account_name', 64)->nullable();
            $table->decimal('available_balance', 20, 8)->default(0);
            $table->decimal('locked_balance', 20, 8)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'account_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_accounts');
    }
};