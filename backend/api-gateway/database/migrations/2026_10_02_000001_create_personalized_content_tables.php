<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personalized_contents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('content_uuid')->unique();
            $table->string('type', 48)->index();
            $table->string('source_type', 32)->index();
            $table->string('source_id', 160)->nullable();
            $table->string('source_provider', 80)->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->string('title', 160);
            $table->string('subtitle', 240)->nullable();
            $table->text('body')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('icon', 48)->nullable();
            $table->string('badge', 48)->nullable();
            $table->string('cta_label', 48)->nullable();
            $table->string('cta_route', 80)->nullable();
            $table->json('cta_payload')->nullable();
            $table->string('related_product', 48)->nullable()->index();
            $table->string('related_asset', 24)->nullable()->index();
            $table->string('related_entity_type', 64)->nullable();
            $table->string('related_entity_id', 120)->nullable();
            $table->unsignedSmallInteger('priority')->default(50)->index();
            $table->string('severity', 16)->default('INFO');
            $table->string('status', 20)->default('DRAFT')->index();
            $table->json('target_interests')->nullable();
            $table->json('target_products')->nullable();
            $table->json('target_assets')->nullable();
            $table->json('target_experience_modes')->nullable();
            $table->json('target_regions')->nullable();
            $table->json('target_countries')->nullable();
            $table->json('target_user_segments')->nullable();
            $table->unsignedTinyInteger('minimum_kyc_tier')->default(0);
            $table->json('eligibility_rules')->nullable();
            $table->unsignedSmallInteger('personalization_weight')->default(50);
            $table->unsignedSmallInteger('frequency_cap')->default(5);
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'publish_at', 'expires_at', 'priority'], 'personalized_content_delivery_idx');
        });

        Schema::create('personalized_content_interactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_id')->constrained('personalized_contents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('interaction_type', 20)->index();
            $table->string('surface', 32)->default('DASHBOARD')->index();
            $table->unsignedSmallInteger('position')->nullable();
            $table->uuid('event_uuid')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'content_id', 'interaction_type', 'created_at'], 'content_user_interaction_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personalized_content_interactions');
        Schema::dropIfExists('personalized_contents');
    }
};
