<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('developer_projects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('project_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('environment', 20)->default('sandbox')->index();
            $table->string('status', 30)->default('active')->index();
            $table->string('tier', 40)->default('standard');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'environment', 'status']);
        });

        Schema::create('developer_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->uuid('key_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('developer_projects')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('environment', 20)->default('sandbox')->index();
            $table->string('key_prefix', 40)->index();
            $table->string('key_hash', 128)->unique();
            $table->text('encrypted_secret');
            $table->string('secret_hash', 128);
            $table->string('passphrase_hash', 128)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });

        Schema::create('developer_api_key_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_key_id')->constrained('developer_api_keys')->cascadeOnDelete();
            $table->string('permission', 80);
            $table->timestamps();
            $table->unique(['api_key_id', 'permission']);
        });

        Schema::create('developer_api_key_ip_whitelists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_key_id')->constrained('developer_api_keys')->cascadeOnDelete();
            $table->string('cidr', 80);
            $table->timestamps();
            $table->unique(['api_key_id', 'cidr']);
        });

        Schema::create('developer_api_nonces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_key_id')->constrained('developer_api_keys')->cascadeOnDelete();
            $table->string('nonce', 120);
            $table->timestamp('seen_at');
            $table->timestamps();
            $table->unique(['api_key_id', 'nonce']);
            $table->index('seen_at');
        });

        Schema::create('developer_api_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('request_id', 80)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('developer_projects')->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained('developer_api_keys')->nullOnDelete();
            $table->string('environment', 20)->nullable();
            $table->string('method', 12);
            $table->string('path', 255);
            $table->unsignedInteger('status_code')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('ip_address', 80)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['api_key_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
        });

        Schema::create('developer_webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->uuid('endpoint_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('developer_projects')->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('status', 30)->default('active')->index();
            $table->json('events')->nullable();
            $table->text('encrypted_secret');
            $table->timestamp('secret_rotated_at')->nullable();
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('developer_webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('delivery_uuid')->unique();
            $table->uuid('event_id')->index();
            $table->foreignId('endpoint_id')->constrained('developer_webhook_endpoints')->cascadeOnDelete();
            $table->string('event_type', 120);
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('last_status_code')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('dead_lettered_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });

        Schema::create('developer_realtime_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('developer_projects')->nullOnDelete();
            $table->string('environment', 20)->default('sandbox')->index();
            $table->string('stream', 120);
            $table->unsignedBigInteger('sequence');
            $table->string('event_type', 120);
            $table->json('payload');
            $table->timestamp('created_at');
            $table->unique(['project_id', 'stream', 'sequence']);
            $table->index(['project_id', 'stream', 'created_at']);
        });

        Schema::create('developer_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('developer_projects')->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained('developer_api_keys')->nullOnDelete();
            $table->string('event_type', 120)->index();
            $table->string('severity', 20)->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('developer_sandbox_faucet_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('developer_projects')->cascadeOnDelete();
            $table->string('asset', 20);
            $table->decimal('amount', 28, 8);
            $table->timestamp('claimed_at');
            $table->timestamps();
            $table->index(['project_id', 'asset', 'claimed_at']);
        });

        Schema::create('developer_sandbox_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('developer_projects')->cascadeOnDelete();
            $table->string('asset', 20);
            $table->decimal('available', 28, 8)->default(0);
            $table->decimal('reserved', 28, 8)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'asset']);
            $table->index(['user_id', 'asset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_sandbox_balances');
        Schema::dropIfExists('developer_realtime_events');
        Schema::dropIfExists('developer_sandbox_faucet_claims');
        Schema::dropIfExists('developer_audit_logs');
        Schema::dropIfExists('developer_webhook_deliveries');
        Schema::dropIfExists('developer_webhook_endpoints');
        Schema::dropIfExists('developer_api_request_logs');
        Schema::dropIfExists('developer_api_nonces');
        Schema::dropIfExists('developer_api_key_ip_whitelists');
        Schema::dropIfExists('developer_api_key_permissions');
        Schema::dropIfExists('developer_api_keys');
        Schema::dropIfExists('developer_projects');
    }
};
