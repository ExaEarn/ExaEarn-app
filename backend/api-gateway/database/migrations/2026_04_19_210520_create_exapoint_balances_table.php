<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('exapoint_balances')) {
            Schema::create('exapoint_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('available_points', 20, 8)->default(0);
                $table->decimal('locked_points', 20, 8)->default(0);
                $table->decimal('total_earned', 20, 8)->default(0);
                $table->decimal('total_spent', 20, 8)->default(0);
                $table->timestamps();

                $table->unique('user_id');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exapoint_balances');
    }
};
