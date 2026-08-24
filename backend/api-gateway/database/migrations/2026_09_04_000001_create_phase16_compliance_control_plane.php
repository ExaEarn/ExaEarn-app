<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'verified_country')) {
                $table->string('verified_country', 2)->nullable()->after('kyc_level')->index();
            }
            if (! Schema::hasColumn('users', 'residence_country')) {
                $table->string('residence_country', 2)->nullable()->after('verified_country')->index();
            }
            if (! Schema::hasColumn('users', 'account_status')) {
                $table->string('account_status', 48)->default('FULLY_ACTIVE')->after('residence_country')->index();
            }
        });

        Schema::create('compliance_jurisdictions', function (Blueprint $table): void {
            $table->id();
            $table->string('country_code', 2)->unique();
            $table->string('country_name', 120);
            $table->string('region', 80)->nullable()->index();
            $table->string('status', 40)->default('UNCONFIGURED')->index();
            $table->string('risk_level', 40)->default('UNRATED')->index();
            $table->string('policy_version', 80)->default('v1');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_code', 80)->unique();
            $table->string('risk_category', 40)->default('STANDARD')->index();
            $table->string('default_policy', 40)->default('REQUIRE_KYC');
            $table->string('technical_feature_flag', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_policy_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('rule_uuid')->unique();
            $table->string('scope', 40)->default('GLOBAL')->index();
            $table->string('jurisdiction', 2)->nullable()->index();
            $table->string('region', 80)->nullable()->index();
            $table->string('product_code', 80)->nullable()->index();
            $table->string('account_type', 40)->nullable()->index();
            $table->string('asset', 24)->nullable()->index();
            $table->string('market_symbol', 48)->nullable()->index();
            $table->string('network', 80)->nullable()->index();
            $table->string('currency', 24)->nullable()->index();
            $table->string('payment_method', 80)->nullable()->index();
            $table->string('decision', 40);
            $table->string('reason_code', 120);
            $table->unsignedTinyInteger('required_kyc_level')->default(0);
            $table->string('required_kyb_tier', 40)->nullable();
            $table->decimal('max_amount', 36, 18)->nullable();
            $table->unsignedInteger('max_leverage')->nullable();
            $table->json('limits')->nullable();
            $table->unsignedInteger('precedence')->default(100);
            $table->string('status', 40)->default('ACTIVE')->index();
            $table->string('policy_version', 80)->default('v1')->index();
            $table->foreignId('submitted_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('legal_reference', 180)->nullable();
            $table->timestamp('effective_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'product_code', 'jurisdiction']);
        });

        Schema::create('compliance_policy_changes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('change_uuid')->unique();
            $table->foreignId('rule_id')->nullable()->constrained('compliance_policy_rules')->nullOnDelete();
            $table->string('change_type', 80);
            $table->string('status', 40)->default('PENDING_APPROVAL')->index();
            $table->json('previous_value')->nullable();
            $table->json('new_value');
            $table->foreignId('submitted_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('reason');
            $table->string('legal_reference', 180)->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_user_restrictions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('restriction_uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->cascadeOnDelete();
            $table->string('restriction_type', 80)->index();
            $table->string('status', 40)->default('ACTIVE')->index();
            $table->string('reason_code', 120);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'restriction_type', 'status']);
            $table->index(['institution_id', 'restriction_type', 'status']);
        });

        Schema::create('compliance_policy_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('exception_uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->cascadeOnDelete();
            $table->string('product_code', 80)->nullable()->index();
            $table->string('asset', 24)->nullable();
            $table->string('market_symbol', 48)->nullable();
            $table->string('decision', 40)->default('ALLOW');
            $table->string('status', 40)->default('ACTIVE')->index();
            $table->text('reason');
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_decision_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('decision_uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->nullOnDelete();
            $table->string('actor_type', 40)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('product_code', 80)->index();
            $table->string('action', 80);
            $table->string('jurisdiction', 2)->nullable()->index();
            $table->string('account_type', 40)->nullable();
            $table->string('asset', 24)->nullable();
            $table->string('market_symbol', 48)->nullable();
            $table->string('network', 80)->nullable();
            $table->string('currency', 24)->nullable();
            $table->string('decision', 40)->index();
            $table->string('reason_code', 120)->index();
            $table->string('policy_version', 80);
            $table->json('effective_limits')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('decided_at')->index();
            $table->timestamps();
        });

        Schema::create('compliance_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('case_uuid')->unique();
            $table->string('case_type', 80)->index();
            $table->string('severity', 40)->default('MEDIUM')->index();
            $table->string('status', 40)->default('OPEN')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutional_accounts')->nullOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->json('evidence')->nullable();
            $table->json('review_history')->nullable();
            $table->text('decision')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_cases');
        Schema::dropIfExists('compliance_decision_logs');
        Schema::dropIfExists('compliance_policy_exceptions');
        Schema::dropIfExists('compliance_user_restrictions');
        Schema::dropIfExists('compliance_policy_changes');
        Schema::dropIfExists('compliance_policy_rules');
        Schema::dropIfExists('compliance_products');
        Schema::dropIfExists('compliance_jurisdictions');
        Schema::table('users', function (Blueprint $table): void {
            foreach (['account_status', 'residence_country', 'verified_country'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
