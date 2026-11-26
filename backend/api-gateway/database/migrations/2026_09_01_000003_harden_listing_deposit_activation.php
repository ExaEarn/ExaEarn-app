<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_deposit_readiness_checks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('check_uuid')->unique();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            // The referenced network configuration table is created by the later 2026-09-08 migration.
            // Add its foreign key in the follow-up migration after that table exists.
            $table->unsignedBigInteger('listing_asset_network_configuration_id');
            $table->string('configuration_fingerprint', 64)->index();
            $table->string('overall_status', 32)->index();
            $table->json('gates');
            $table->json('evidence')->nullable();
            $table->string('correlation_id', 80)->index();
            $table->foreignId('checked_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('checked_at')->index();
            $table->timestamps();
        });

        Schema::create('listing_deposit_activation_approvals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('approval_uuid')->unique();
            $table->foreignId('application_id')->constrained('listing_applications')->cascadeOnDelete();
            $table->string('configuration_fingerprint', 64)->index();
            $table->string('status', 32)->default('REQUESTED')->index();
            $table->foreignId('requested_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('reason');
            $table->json('readiness_snapshot');
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_deposit_activation_approvals');
        Schema::dropIfExists('listing_deposit_readiness_checks');
    }
};
