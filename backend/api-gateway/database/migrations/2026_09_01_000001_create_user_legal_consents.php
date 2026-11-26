<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_legal_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('consent_type', 80);
            $table->string('document_version', 80);
            $table->timestamp('accepted_at');
            $table->string('ip_address_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'consent_type', 'document_version'], 'user_legal_consent_unique');
            $table->index(['consent_type', 'document_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_legal_consents');
    }
};
