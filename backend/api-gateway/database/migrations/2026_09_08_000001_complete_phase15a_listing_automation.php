<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_asset_network_configurations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('network_config_uuid')->unique();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            $table->foreignId('listing_asset_configuration_id')->nullable()->constrained('listing_asset_configurations')->nullOnDelete();
            $table->foreignId('blockchain_network_id')->constrained('blockchain_networks')->restrictOnDelete();
            $table->foreignId('blockchain_asset_id')->nullable()->constrained('blockchain_assets')->nullOnDelete();
            $table->string('network', 64)->index();
            $table->string('token_standard', 40);
            $table->string('contract_address', 180)->nullable();
            $table->unsignedTinyInteger('decimals');
            $table->boolean('deposit_enabled')->default(false);
            $table->boolean('withdrawal_enabled')->default(false);
            $table->unsignedInteger('required_confirmations')->default(1);
            $table->unsignedInteger('finality_confirmations')->default(1);
            $table->decimal('minimum_deposit', 36, 18)->default('0');
            $table->decimal('minimum_withdrawal', 36, 18)->default('0');
            $table->decimal('withdrawal_fee', 36, 18)->default('0');
            $table->boolean('memo_required')->default(false);
            $table->string('explorer_url')->nullable();
            $table->string('status', 64)->default('CONFIGURED')->index();
            $table->string('validation_status', 64)->default('NOT_RUN')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['network', 'contract_address'], 'listing_asset_network_contract_unique');
            $table->index(['application_id', 'network', 'status'], 'listing_asset_network_status_index');
        });

        Schema::create('listing_contract_validations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('validation_uuid')->unique();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            $table->foreignId('listing_asset_network_configuration_id')->nullable()->constrained('listing_asset_network_configurations')->nullOnDelete();
            $table->string('network', 64)->index();
            $table->string('contract_address', 180)->nullable();
            $table->string('status', 64)->index();
            $table->json('submitted_metadata')->nullable();
            $table->json('validated_metadata')->nullable();
            $table->json('risk_flags')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();
            $table->unique(['network', 'contract_address', 'application_id'], 'listing_contract_validation_unique');
        });

        Schema::create('listing_launch_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            $table->string('event_type', 80)->index();
            $table->string('status', 64)->default('PENDING')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('executed_at')->nullable();
            $table->string('idempotency_key', 180)->unique();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->index(['application_id', 'event_type', 'status'], 'listing_launch_event_status_index');
        });

        Schema::create('listing_token_migrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('migration_uuid')->unique();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            $table->string('migration_type', 80)->index();
            $table->string('old_network', 64)->nullable();
            $table->string('old_contract_address', 180)->nullable();
            $table->string('new_network', 64)->nullable();
            $table->string('new_contract_address', 180)->nullable();
            $table->string('status', 64)->default('DRAFT')->index();
            $table->text('reason');
            $table->json('plan')->nullable();
            $table->foreignId('requested_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_token_migrations');
        Schema::dropIfExists('listing_launch_events');
        Schema::dropIfExists('listing_contract_validations');
        Schema::dropIfExists('listing_asset_network_configurations');
    }
};

