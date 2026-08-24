<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_obligations', function (Blueprint $table): void {
            $table->id();
            $table->string('obligation_uuid')->unique();
            $table->string('obligation_type', 32)->index(); // RECEIVABLE / PAYABLE
            $table->string('counterparty_type', 80)->index();
            $table->string('counterparty_reference', 180)->nullable()->index();
            $table->string('source_service', 96)->index();
            $table->string('source_reference', 180)->index();
            $table->string('asset', 32)->index();
            $table->decimal('original_amount', 36, 18);
            $table->decimal('outstanding_amount', 36, 18);
            $table->string('status', 40)->default('OPEN')->index();
            $table->date('due_date')->nullable()->index();
            $table->string('ledger_reference', 180)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['obligation_type', 'source_service', 'source_reference'], 'finance_obligation_source_unique');
        });

        Schema::create('finance_opening_balance_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('import_uuid')->unique();
            $table->foreignId('requested_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->string('asset', 32)->index();
            $table->decimal('amount', 36, 18);
            $table->string('debit_account_type', 120);
            $table->string('credit_account_type', 120);
            $table->string('ownership_class', 64)->index();
            $table->string('status', 40)->default('PENDING_APPROVAL')->index();
            $table->string('ledger_reference', 180)->nullable();
            $table->json('evidence');
            $table->text('reason');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_opening_balance_imports');
        Schema::dropIfExists('finance_obligations');
    }
};
