<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method_type', 64);
            $table->string('display_name', 120)->nullable();
            $table->string('fiat_currency', 16)->default('NGN');
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_code', 32)->nullable();
            $table->string('account_name', 160)->nullable();
            $table->string('account_number', 64)->nullable();
            $table->text('payment_note')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_enabled']);
            $table->index(['method_type', 'fiat_currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_payment_methods');
    }
};
