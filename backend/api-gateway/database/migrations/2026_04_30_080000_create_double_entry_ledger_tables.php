<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('account_type', 32);
                $table->string('asset', 16);
                $table->decimal('balance', 36, 18)->default(0);
                $table->timestamps();

                $table->unique(['user_id', 'account_type', 'asset']);
                $table->index(['account_type', 'asset']);
            });
        }

        if (!Schema::hasTable('ledger_transactions')) {
            Schema::create('ledger_transactions', function (Blueprint $table): void {
                $table->id();
                $table->string('reference', 100)->unique();
                $table->string('description', 255);
                $table->string('status', 20)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ledger_entries')) {
            Schema::create('ledger_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('asset', 16);
                $table->decimal('amount', 36, 18);
                $table->decimal('balance_after', 36, 18);
                $table->string('reference', 100);
                $table->string('transaction_type', 32);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('account_id');
                $table->index('reference');
                $table->index(['user_id', 'asset']);
                $table->foreign('reference')->references('reference')->on('ledger_transactions')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('accounts');
    }
};
