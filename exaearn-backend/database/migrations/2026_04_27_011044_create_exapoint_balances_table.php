<?php

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
        if (!Schema::hasTable('exapoint_balances')) {
            Schema::create('exapoint_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
                $table->decimal('available_points', 20, 8)->default(0);
                $table->decimal('locked_points', 20, 8)->default(0);
                $table->decimal('total_earned', 20, 8)->default(0);
                $table->decimal('total_spent', 20, 8)->default(0);
                $table->timestamps();
                $table->index('user_id');
            });

            return;
        }

        Schema::table('exapoint_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('exapoint_balances', 'total_earned')) {
                $table->decimal('total_earned', 20, 8)->default(0)->after('locked_points');
            }

            if (!Schema::hasColumn('exapoint_balances', 'total_spent')) {
                $table->decimal('total_spent', 20, 8)->default(0)->after('total_earned');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exapoint_balances');
    }
};
