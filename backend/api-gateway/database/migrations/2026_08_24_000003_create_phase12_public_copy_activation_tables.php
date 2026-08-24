<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('copy_public_settings')) {
            Schema::create('copy_public_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 120)->unique();
                $table->string('value', 120);
                $table->json('metadata')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('copy_jurisdiction_rules')) {
            Schema::create('copy_jurisdiction_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('country', 2)->unique();
                $table->string('spot_copy_public', 24)->default('DISABLED');
                $table->string('futures_copy_public', 24)->default('DISABLED');
                $table->string('profit_share_public', 24)->default('DISABLED');
                $table->unsignedSmallInteger('max_leverage')->default(1);
                $table->string('terms_version', 80)->nullable();
                $table->string('status', 24)->default('DISABLED')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('copy_market_eligibilities')) {
            Schema::create('copy_market_eligibilities', function (Blueprint $table): void {
                $table->id();
                $table->string('symbol', 32)->unique();
                $table->boolean('spot_copy_public_enabled')->default(false);
                $table->boolean('futures_copy_public_enabled')->default(false);
                $table->decimal('minimum_liquidity', 36, 18)->default('0');
                $table->decimal('maximum_copy_aum', 36, 18)->default('0');
                $table->decimal('maximum_copy_concentration', 18, 8)->default('0');
                $table->decimal('maximum_slippage_bps', 18, 8)->default('100');
                $table->string('risk_tier', 24)->default('standard');
                $table->string('status', 24)->default('DISABLED')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('copy_terms')) {
            Schema::create('copy_terms', function (Blueprint $table): void {
                $table->id();
                $table->string('type', 60);
                $table->string('version', 80);
                $table->string('status', 24)->default('DRAFT')->index();
                $table->text('summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['type', 'version'], 'copy_terms_type_version_unique');
            });
        }

        if (!Schema::hasTable('copy_term_acceptances')) {
            Schema::create('copy_term_acceptances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 60);
                $table->string('version', 80);
                $table->timestamp('accepted_at');
                $table->string('ip_hash', 128)->nullable();
                $table->string('user_agent_hash', 128)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'type', 'version'], 'copy_terms_acceptance_unique');
            });
        }

        if (!Schema::hasTable('copy_complaints')) {
            Schema::create('copy_complaints', function (Blueprint $table): void {
                $table->id();
                $table->uuid('complaint_id')->unique();
                $table->foreignId('follower_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('lead_trader_id')->nullable()->constrained('traders')->nullOnDelete();
                $table->foreignId('copy_relationship_id')->nullable()->constrained('copy_relationships')->nullOnDelete();
                $table->foreignId('copy_order_id')->nullable()->constrained('copy_orders')->nullOnDelete();
                $table->foreignId('lead_trade_event_id')->nullable()->constrained('copy_lead_trade_events')->nullOnDelete();
                $table->string('category', 80)->index();
                $table->string('status', 32)->default('OPEN')->index();
                $table->text('message');
                $table->json('evidence')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('resolution', 160)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('copy_public_activation_requests')) {
            Schema::create('copy_public_activation_requests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('request_id')->unique();
                $table->string('requested_mode', 32);
                $table->string('status', 32)->default('REQUESTED')->index();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reason', 500);
                $table->json('readiness_snapshot');
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_public_activation_requests');
        Schema::dropIfExists('copy_complaints');
        Schema::dropIfExists('copy_term_acceptances');
        Schema::dropIfExists('copy_terms');
        Schema::dropIfExists('copy_market_eligibilities');
        Schema::dropIfExists('copy_jurisdiction_rules');
        Schema::dropIfExists('copy_public_settings');
    }
};
