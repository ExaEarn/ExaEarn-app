<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_organizations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('organization_uuid')->unique();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('project_name');
            $table->string('jurisdiction', 120);
            $table->string('website')->nullable();
            $table->string('business_email')->nullable();
            $table->json('registration_details')->nullable();
            $table->date('incorporation_date')->nullable();
            $table->json('registered_address')->nullable();
            $table->string('project_category', 80)->nullable();
            $table->json('primary_contact')->nullable();
            $table->json('authorized_representative')->nullable();
            $table->string('status', 40)->default('ACTIVE')->index();
            $table->timestamps();
        });

        Schema::create('listing_team_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('listing_organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role', 32);
            $table->string('status', 40)->default('ACTIVE');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'email']);
        });

        Schema::create('listing_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('application_uuid')->unique();
            $table->string('reference')->unique();
            $table->foreignId('organization_id')->constrained('listing_organizations')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('application_type', 64);
            $table->string('application_status', 64)->default('DRAFT')->index();
            $table->string('integration_status', 64)->default('NOT_STARTED')->index();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->json('project_information')->nullable();
            $table->json('asset_information')->nullable();
            $table->json('blockchain_information')->nullable();
            $table->json('tokenomics')->nullable();
            $table->json('technology')->nullable();
            $table->json('security')->nullable();
            $table->json('legal_compliance')->nullable();
            $table->json('market_community')->nullable();
            $table->json('liquidity')->nullable();
            $table->json('listing_request')->nullable();
            $table->json('verified_metadata')->nullable();
            $table->json('risk_flags')->nullable();
            $table->json('review_summary')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('recommended_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('recommended_at')->nullable();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'idempotency_key'], 'listing_application_idempotency_unique');
            $table->index(['application_type', 'application_status']);
        });

        Schema::create('listing_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('document_uuid')->unique();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 80);
            $table->string('original_name');
            $table->string('storage_disk')->default('private');
            $table->string('storage_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('visibility', 40)->default('PRIVATE');
            $table->string('scan_status', 40)->default('PENDING');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('listing_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('review_uuid')->unique();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            $table->foreignId('reviewer_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('review_type', 64);
            $table->string('status', 40)->default('NOT_STARTED');
            $table->unsignedSmallInteger('score')->nullable();
            $table->json('scorecard')->nullable();
            $table->json('risk_flags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['application_id', 'review_type']);
        });

        Schema::create('listing_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('message_uuid')->unique();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            $table->string('sender_type', 32);
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('message_type', 64)->default('MESSAGE');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('internal_only')->default(false);
            $table->timestamps();
        });

        Schema::create('listing_asset_configurations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('asset_config_uuid')->unique();
            $table->foreignId('application_id')->unique()->constrained('listing_applications')->cascadeOnDelete();
            $table->foreignId('blockchain_asset_id')->nullable()->constrained('blockchain_assets')->nullOnDelete();
            $table->string('asset_uid')->unique();
            $table->string('name');
            $table->string('symbol', 24);
            $table->string('slug')->unique();
            $table->string('asset_type', 40);
            $table->string('network');
            $table->string('token_standard', 40);
            $table->string('contract_address')->nullable();
            $table->unsignedTinyInteger('decimals');
            $table->string('explorer_url')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status', 64)->default('CONFIGURED')->index();
            $table->boolean('deposit_enabled')->default(false);
            $table->boolean('withdrawal_enabled')->default(false);
            $table->boolean('trading_enabled')->default(false);
            $table->json('supply_metadata')->nullable();
            $table->json('configuration_history')->nullable();
            $table->timestamps();
            $table->unique(['network', 'contract_address'], 'listing_asset_contract_unique');
            $table->index(['symbol', 'status']);
        });

        Schema::create('listing_market_configurations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('market_config_uuid')->unique();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->string('symbol', 48);
            $table->string('base_asset', 24);
            $table->string('quote_asset', 24);
            $table->decimal('tick_size', 36, 18)->default('0.00000001');
            $table->decimal('quantity_step', 36, 18)->default('0.00000001');
            $table->decimal('min_quantity', 36, 18)->default('0');
            $table->decimal('max_quantity', 36, 18)->default('0');
            $table->decimal('min_notional', 36, 18)->default('0');
            $table->decimal('maker_fee', 10, 8)->default('0.00100000');
            $table->decimal('taker_fee', 10, 8)->default('0.00100000');
            $table->string('status', 64)->default('PRE_LAUNCH')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['symbol']);
        });

        Schema::create('listing_liquidity_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            $table->foreignId('listing_market_configuration_id')->nullable()->constrained('listing_market_configurations')->nullOnDelete();
            $table->string('arrangement', 64);
            $table->string('market_maker_reference')->nullable();
            $table->decimal('required_base_liquidity', 36, 18)->default('0');
            $table->decimal('required_quote_liquidity', 36, 18)->default('0');
            $table->decimal('maximum_spread_bps', 18, 8)->default('0');
            $table->decimal('minimum_depth', 36, 18)->default('0');
            $table->string('liquidity_status', 64)->default('LIQUIDITY_NOT_CONFIGURED');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('listing_test_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('test_run_uuid')->unique();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            $table->foreignId('requested_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('environment', 32)->default('staging');
            $table->string('overall_status', 32)->default('PENDING');
            $table->json('results');
            $table->json('critical_failures')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('listing_launch_schedules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('schedule_uuid')->unique();
            $table->foreignId('application_id')->unique()->constrained('listing_applications')->cascadeOnDelete();
            $table->timestamp('announcement_at')->nullable();
            $table->timestamp('deposit_open_at')->nullable();
            $table->timestamp('trading_open_at');
            $table->timestamp('withdrawal_open_at')->nullable();
            $table->string('status', 64)->default('SCHEDULED');
            $table->json('announcement_metadata')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('listing_monitoring_alerts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('alert_uuid')->unique();
            $table->foreignId('application_id')->nullable()->constrained('listing_applications')->nullOnDelete();
            $table->string('asset', 24)->nullable();
            $table->string('market_symbol', 48)->nullable();
            $table->string('alert_type', 80);
            $table->string('severity', 32)->default('WARNING');
            $table->string('status', 40)->default('OPEN');
            $table->json('evidence')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity']);
        });

        Schema::create('listing_unknown_asset_deposits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('network');
            $table->string('contract_address')->nullable();
            $table->string('transaction_hash');
            $table->string('address');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 36, 18);
            $table->string('status', 40)->default('REVIEW_REQUIRED');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['network', 'contract_address', 'transaction_hash'], 'listing_unknown_asset_unique');
        });

        Schema::create('listing_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('audit_uuid')->unique();
            $table->foreignId('application_id')->nullable()->constrained('listing_applications')->nullOnDelete();
            $table->string('actor_type', 32);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 100);
            $table->string('resource_type', 80)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach ([
            'listing_audit_logs',
            'listing_unknown_asset_deposits',
            'listing_monitoring_alerts',
            'listing_launch_schedules',
            'listing_test_runs',
            'listing_liquidity_requirements',
            'listing_market_configurations',
            'listing_asset_configurations',
            'listing_messages',
            'listing_reviews',
            'listing_documents',
            'listing_applications',
            'listing_team_members',
            'listing_organizations',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
