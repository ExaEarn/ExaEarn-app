<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 16);
            $table->string('address', 255);
            $table->string('network', 32);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'currency', 'network']);
            $table->index('address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_addresses');
    }
};

