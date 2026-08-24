<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('margin_realtime_events')) {
            Schema::create('margin_realtime_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('margin_account_id')->nullable()->constrained('margin_accounts')->nullOnDelete();
                $table->unsignedBigInteger('sequence');
                $table->string('event', 80);
                $table->json('payload');
                $table->timestamp('published_at');
                $table->timestamps();

                $table->unique(['user_id', 'sequence'], 'margin_realtime_user_sequence_unique');
                $table->index(['user_id', 'margin_account_id', 'sequence'], 'margin_realtime_user_account_sequence_idx');
                $table->index(['event', 'published_at']);
            });
        }

        if (! Schema::hasTable('margin_load_runs')) {
            Schema::create('margin_load_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('run_id')->unique();
                $table->unsignedInteger('iterations');
                $table->unsignedInteger('operations');
                $table->unsignedInteger('failures')->default(0);
                $table->decimal('duration_ms', 18, 6)->default('0');
                $table->string('status', 32)->default('PASS');
                $table->json('metrics')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('margin_load_runs');
        Schema::dropIfExists('margin_realtime_events');
    }
};
