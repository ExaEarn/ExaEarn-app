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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->string('event'); // 'queued', 'sent', 'failed', 'read'
            $table->string('channel'); // 'email', 'push', 'in_app'
            $table->string('provider')->nullable(); // 'mailgun', 'firebase', etc.
            $table->json('details')->nullable(); // Provider-specific response data
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('notification_id');
            $table->index('event');
            $table->index('channel');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
