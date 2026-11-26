<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_organizations', function (Blueprint $table): void {
            $table->string('trading_name')->nullable();
            $table->string('organization_type', 40)->nullable()->index();
            $table->string('operating_country', 2)->nullable()->index();
            $table->string('registered_country', 2)->nullable()->index();
            $table->text('business_description')->nullable();
            $table->json('business_information')->nullable();
            $table->json('regulatory_information')->nullable();
            $table->json('ownership_structure')->nullable();
            $table->string('kyb_status', 40)->default('DRAFT')->index();
            $table->unsignedTinyInteger('kyb_progress')->default(0);
            $table->string('kyb_current_stage', 80)->default('ORGANIZATION');
            $table->timestamp('kyb_submitted_at')->nullable();
            $table->timestamp('kyb_reviewed_at')->nullable();
            $table->string('registry_verification_status', 40)->default('NOT_CHECKED');
            $table->string('screening_status', 40)->default('NOT_CHECKED');
        });

        Schema::create('listing_organization_people', function (Blueprint $table): void {
            $table->id();
            $table->uuid('person_uuid')->unique();
            $table->foreignId('organization_id')->constrained('listing_organizations')->cascadeOnDelete();
            $table->foreignId('linked_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('person_type', 32)->index();
            $table->string('full_legal_name');
            $table->string('role_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('nationality', 2)->nullable();
            $table->string('residence_country', 2)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->decimal('ownership_percentage', 8, 4)->nullable();
            $table->decimal('control_percentage', 8, 4)->nullable();
            $table->string('ownership_type', 40)->nullable();
            $table->string('relationship')->nullable();
            $table->text('professional_profile')->nullable();
            $table->string('authority_basis')->nullable();
            $table->boolean('authority_confirmed')->default(false);
            $table->string('identification_status', 40)->default('NOT_CHECKED');
            $table->string('sanctions_status', 40)->default('NOT_CHECKED');
            $table->string('pep_status', 40)->default('NOT_CHECKED');
            $table->string('adverse_media_status', 40)->default('NOT_CHECKED');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'person_type']);
        });

        Schema::create('listing_organization_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('document_uuid')->unique();
            $table->foreignId('organization_id')->constrained('listing_organizations')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('document_type', 64)->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('original_name');
            $table->string('storage_disk');
            $table->string('storage_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64)->index();
            $table->string('access_classification', 32)->default('KYB_PRIVATE');
            $table->string('scan_status', 40)->default('NOT_CONNECTED');
            $table->string('review_status', 40)->default('PENDING');
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->date('expiry_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'document_type', 'version'], 'listing_kyb_document_version_unique');
        });

        Schema::create('listing_organization_declarations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('listing_organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('declaration_type', 64);
            $table->string('declaration_version', 80);
            $table->timestamp('accepted_at');
            $table->string('ip_address_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'declaration_type', 'declaration_version'], 'listing_kyb_declaration_unique');
        });

        Schema::create('listing_organization_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('invitation_uuid')->unique();
            $table->foreignId('organization_id')->constrained('listing_organizations')->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->string('email')->index();
            $table->string('role', 32);
            $table->string('token_hash', 64)->unique();
            $table->string('status', 32)->default('PENDING')->index();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('listing_organization_kyb_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('review_uuid')->unique();
            $table->foreignId('organization_id')->constrained('listing_organizations')->cascadeOnDelete();
            $table->foreignId('reviewer_admin_id')->constrained('admins')->restrictOnDelete();
            $table->string('from_status', 40);
            $table->string('to_status', 40);
            $table->text('public_message')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('manual_verification_evidence')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['organization_id', 'to_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_organization_kyb_reviews');
        Schema::dropIfExists('listing_organization_invitations');
        Schema::dropIfExists('listing_organization_declarations');
        Schema::dropIfExists('listing_organization_documents');
        Schema::dropIfExists('listing_organization_people');
        Schema::table('listing_organizations', function (Blueprint $table): void {
            $table->dropColumn(['trading_name', 'organization_type', 'operating_country', 'registered_country', 'business_description', 'business_information', 'regulatory_information', 'ownership_structure', 'kyb_status', 'kyb_progress', 'kyb_current_stage', 'kyb_submitted_at', 'kyb_reviewed_at', 'registry_verification_status', 'screening_status']);
        });
    }
};
