<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crowdfunding_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('crowdfunding_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('crowdfunding_comments')->nullOnDelete();
            $table->string('type', 24)->default('COMMENT');
            $table->text('body');
            $table->string('status', 24)->default('ACTIVE');
            $table->boolean('is_creator_reply')->default(false);
            $table->timestamp('reported_at')->nullable();
            $table->json('report_metadata')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('moderation_reason')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('crowdfunding_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('crowdfunding_campaigns')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('document_type', 48);
            $table->string('visibility', 24)->default('PRIVATE');
            $table->string('storage_disk', 40)->default('local');
            $table->string('storage_reference');
            $table->string('safe_filename');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('status', 32)->default('PENDING_REVIEW');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('review_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'document_type', 'visibility']);
            $table->index(['owner_id', 'status']);
        });

        Schema::create('crowdfunding_operations_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crowdfunding_operations_settings');
        Schema::dropIfExists('crowdfunding_documents');
        Schema::dropIfExists('crowdfunding_comments');
    }
};
