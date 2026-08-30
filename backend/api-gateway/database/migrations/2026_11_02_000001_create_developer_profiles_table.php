<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('developer_organizations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('organization_uuid')->unique();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 140);
            $table->string('status', 30)->default('active')->index();
            $table->string('production_access_status', 30)->default('not_activated')->index();
            $table->timestamps();
        });
        Schema::create('developer_organization_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('developer_organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('owner');
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });
        Schema::create('developer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('active')->index();
            $table->string('onboarding_status', 30)->default('not_started')->index();
            $table->foreignId('default_project_id')->nullable()->constrained('developer_projects')->nullOnDelete();
            $table->foreignId('default_organization_id')->nullable()->constrained('developer_organizations')->nullOnDelete();
            $table->string('developer_type', 30)->nullable();
            $table->string('use_case', 50)->nullable();
            $table->timestamp('developer_terms_accepted_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_profiles');
        Schema::dropIfExists('developer_organization_memberships');
        Schema::dropIfExists('developer_organizations');
    }
};
