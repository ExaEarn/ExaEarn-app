<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farming_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('project_name', 255);
            $table->string('location', 255);
            $table->string('crop_type', 120);
            $table->decimal('farm_size', 12, 2);
            $table->string('farm_size_unit', 24)->default('acres');
            $table->decimal('investment_target', 20, 8);
            $table->unsignedInteger('duration');
            $table->string('duration_unit', 24)->default('months');
            $table->decimal('expected_yield', 20, 8);
            $table->string('yield_unit', 64)->default('tons');
            $table->date('expected_harvest_date')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('blockchain_reference', 255)->nullable();
            $table->jsonb('verification_documents')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'crop_type']);
        });

        Schema::create('farm_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('farming_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('total_shares');
            $table->decimal('price_per_share', 20, 8);
            $table->unsignedBigInteger('shares_available');
            $table->string('ownership_model', 32)->default('hybrid');
            $table->string('token_contract_address', 255)->nullable();
            $table->string('token_symbol', 32)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 255);
            $table->string('location', 255);
            $table->unsignedInteger('experience_years')->default(0);
            $table->string('verification_status', 32)->default('pending');
            $table->jsonb('identity_documents')->nullable();
            $table->jsonb('equipment_details')->nullable();
            $table->jsonb('geo_metadata')->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();

            $table->index(['verification_status']);
        });

        Schema::create('farm_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('farming_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('shares_owned');
            $table->decimal('investment_amount', 20, 8);
            $table->string('status', 32)->default('pending');
            $table->string('ownership_reference', 255)->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('farm_leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('farming_projects')->cascadeOnDelete();
            $table->foreignId('farmer_id')->constrained('farmers')->cascadeOnDelete();
            $table->foreignId('investment_id')->nullable()->constrained('farm_investments')->nullOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->text('lease_terms');
            $table->unsignedTinyInteger('profit_share');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('contract_reference', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('produce_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('farming_projects')->cascadeOnDelete();
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->nullOnDelete();
            $table->string('growth_stage', 64);
            $table->text('update_description');
            $table->jsonb('images')->nullable();
            $table->jsonb('geo_metadata')->nullable();
            $table->decimal('reported_yield', 20, 8)->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->string('verification_status', 32)->default('pending_review');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'growth_stage']);
        });

        Schema::create('agri_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('farming_projects')->nullOnDelete();
            $table->foreignId('investment_id')->nullable()->constrained('farm_investments')->nullOnDelete();
            $table->string('activity_type', 64);
            $table->decimal('reward_amount', 20, 8);
            $table->string('status', 32)->default('pending');
            $table->string('reward_reference', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'activity_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agri_rewards');
        Schema::dropIfExists('produce_tracking');
        Schema::dropIfExists('farm_leases');
        Schema::dropIfExists('farm_investments');
        Schema::dropIfExists('farmers');
        Schema::dropIfExists('farm_shares');
        Schema::dropIfExists('farming_projects');
    }
};

