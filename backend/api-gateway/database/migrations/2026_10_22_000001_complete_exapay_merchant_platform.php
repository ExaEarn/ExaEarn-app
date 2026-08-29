<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table): void {
            if (! Schema::hasColumn('merchants', 'organization_name')) {
                $table->string('organization_name')->nullable()->after('business_name');
            }
            if (! Schema::hasColumn('merchants', 'country')) {
                $table->string('country', 2)->nullable()->after('organization_name');
            }
            if (! Schema::hasColumn('merchants', 'business_type')) {
                $table->string('business_type', 80)->nullable()->after('country');
            }
            if (! Schema::hasColumn('merchants', 'kyb_status')) {
                $table->string('kyb_status', 40)->default('APPLIED')->after('business_type');
            }
            if (! Schema::hasColumn('merchants', 'settlement_account_reference')) {
                $table->string('settlement_account_reference')->nullable()->after('settlement_currency');
            }
            if (! Schema::hasColumn('merchants', 'pricing_profile')) {
                $table->string('pricing_profile', 80)->nullable()->after('settlement_account_reference');
            }
            if (! Schema::hasColumn('merchants', 'environment')) {
                $table->string('environment', 20)->default('SANDBOX')->after('pricing_profile');
            }
            if (! Schema::hasColumn('merchants', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('environment');
            }
        });

        Schema::table('exaearn_pay_intents', function (Blueprint $table): void {
            if (! Schema::hasColumn('exaearn_pay_intents', 'merchant_reference')) {
                $table->string('merchant_reference')->nullable()->after('public_reference');
            }
            if (! Schema::hasColumn('exaearn_pay_intents', 'customer_reference')) {
                $table->string('customer_reference')->nullable()->after('merchant_reference');
            }
            if (! Schema::hasColumn('exaearn_pay_intents', 'environment')) {
                $table->string('environment', 20)->default('SANDBOX')->after('customer_reference');
            }
            if (! Schema::hasColumn('exaearn_pay_intents', 'capture_mode')) {
                $table->string('capture_mode', 20)->default('AUTOMATIC')->after('environment');
            }
            if (! Schema::hasColumn('exaearn_pay_intents', 'payment_method')) {
                $table->string('payment_method', 40)->default('EXAEARN_BALANCE')->after('capture_mode');
            }
            if (! Schema::hasColumn('exaearn_pay_intents', 'provider')) {
                $table->string('provider', 40)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('exaearn_pay_intents', 'provider_reference')) {
                $table->string('provider_reference')->nullable()->after('provider');
            }
            if (! Schema::hasColumn('exaearn_pay_intents', 'provider_fee_amount')) {
                $table->decimal('provider_fee_amount', 36, 18)->default('0')->after('fee_amount');
            }
            if (! Schema::hasColumn('exaearn_pay_intents', 'net_merchant_amount')) {
                $table->decimal('net_merchant_amount', 36, 18)->default('0')->after('provider_fee_amount');
            }
            if (! Schema::hasColumn('exaearn_pay_intents', 'pricing_snapshot')) {
                $table->json('pricing_snapshot')->nullable()->after('net_merchant_amount');
            }
            if (! Schema::hasColumn('exaearn_pay_intents', 'checkout_token_hash')) {
                $table->string('checkout_token_hash')->nullable()->unique()->after('pricing_snapshot');
            }
            if (! Schema::hasColumn('exaearn_pay_intents', 'captured_at')) {
                $table->timestamp('captured_at')->nullable()->after('expires_at');
            }
            if (! Schema::hasColumn('exaearn_pay_intents', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->after('captured_at');
            }
        });

        if (! Schema::hasTable('merchant_team_members')) {
            Schema::create('merchant_team_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role', 40)->default('OWNER');
                $table->json('permissions')->nullable();
                $table->string('status', 40)->default('ACTIVE');
                $table->timestamps();
                $table->unique(['merchant_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('merchant_payment_links')) {
            Schema::create('merchant_payment_links', function (Blueprint $table): void {
                $table->id();
                $table->uuid('link_id')->unique();
                $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $table->string('environment', 20)->default('SANDBOX');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('amount_mode', 20)->default('FIXED');
                $table->decimal('amount', 36, 18)->nullable();
                $table->string('currency', 8);
                $table->unsignedInteger('maximum_uses')->nullable();
                $table->unsignedInteger('uses_count')->default(0);
                $table->string('status', 40)->default('ACTIVE');
                $table->string('success_url')->nullable();
                $table->string('cancel_url')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('merchant_api_keys')) {
            Schema::create('merchant_api_keys', function (Blueprint $table): void {
                $table->id();
                $table->uuid('key_id')->unique();
                $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $table->foreignId('developer_api_key_id')->nullable()->constrained('developer_api_keys')->nullOnDelete();
                $table->string('environment', 20)->default('SANDBOX');
                $table->string('name');
                $table->string('key_prefix')->unique();
                $table->string('key_hash');
                $table->json('scopes')->nullable();
                $table->json('ip_allowlist')->nullable();
                $table->string('status', 40)->default('ACTIVE');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('merchant_webhook_events')) {
            Schema::create('merchant_webhook_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $table->string('event_type');
                $table->string('resource_type')->nullable();
                $table->string('resource_id')->nullable();
                $table->json('payload');
                $table->string('status', 40)->default('PENDING');
                $table->timestamps();
                $table->index(['merchant_id', 'event_type']);
            });
        }

        if (! Schema::hasTable('merchant_reconciliation_runs')) {
            Schema::create('merchant_reconciliation_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('run_id')->unique();
                $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
                $table->string('status', 40)->default('PASS');
                $table->json('summary')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('merchant_reconciliation_differences')) {
            Schema::create('merchant_reconciliation_differences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('merchant_reconciliation_run_id')->constrained('merchant_reconciliation_runs')->cascadeOnDelete();
                $table->string('severity', 40)->default('WARNING');
                $table->string('type');
                $table->string('resource_id')->nullable();
                $table->decimal('difference_amount', 36, 18)->default('0');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('merchant_risk_signals')) {
            Schema::create('merchant_risk_signals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('signal_id')->unique();
                $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $table->string('signal_type');
                $table->string('severity', 40)->default('WARNING');
                $table->string('decision', 40)->default('REVIEW');
                $table->json('evidence')->nullable();
                $table->string('status', 40)->default('OPEN');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'merchant_risk_signals',
            'merchant_reconciliation_differences',
            'merchant_reconciliation_runs',
            'merchant_webhook_events',
            'merchant_api_keys',
            'merchant_payment_links',
            'merchant_team_members',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
