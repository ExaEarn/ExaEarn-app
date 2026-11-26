<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_documents', function (Blueprint $table): void {
            $table->string('checksum_sha256', 64)->nullable()->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('review_status', 40)->default('PENDING')->index();
            $table->foreignId('reviewer_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unique(['application_id', 'document_type', 'version'], 'listing_document_version_unique');
        });
    }

    public function down(): void
    {
        Schema::table('listing_documents', function (Blueprint $table): void {
            $table->dropUnique('listing_document_version_unique');
            $table->dropForeign(['reviewer_admin_id']);
            $table->dropColumn(['checksum_sha256', 'version', 'review_status', 'reviewer_admin_id', 'reviewed_at']);
        });
    }
};
