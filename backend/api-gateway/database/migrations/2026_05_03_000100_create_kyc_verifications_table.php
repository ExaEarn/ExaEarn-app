<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kyc_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('level')->default(0);
            $table->string('document');
            $table->string('selfie');
            $table->string('document_type');
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->json('risk_flags')->nullable();
            $table->boolean('auto_verified')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'kyc_level')) {
                $table->unsignedTinyInteger('kyc_level')->default(0)->after('kyc_verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'kyc_level')) {
                $table->dropColumn('kyc_level');
            }
        });

        Schema::dropIfExists('kyc_verifications');
    }
};
