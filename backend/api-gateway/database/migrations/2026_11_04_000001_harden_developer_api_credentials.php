<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('developer_api_keys', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->after('project_id')->constrained('users')->nullOnDelete();
            $table->timestamp('disabled_at')->nullable()->after('expires_at');
            $table->timestamp('revoked_at')->nullable()->after('disabled_at');
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            $table->index(['project_id', 'environment', 'status'], 'developer_key_project_environment_status');
        });
        Schema::create('developer_api_realtime_sessions',function(Blueprint $table):void{
            $table->id();$table->uuid('session_uuid')->unique();
            $table->foreignId('api_key_id')->constrained('developer_api_keys')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('developer_projects')->cascadeOnDelete();
            $table->string('environment',20)->index();$table->string('token_hash',128)->unique();
            $table->string('status',20)->default('active')->index();$table->json('topics');
            $table->timestamp('expires_at')->index();$table->timestamp('revoked_at')->nullable();$table->timestamps();
            $table->index(['api_key_id','status','expires_at'],'developer_realtime_key_status_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_api_realtime_sessions');
        Schema::table('developer_api_keys', function (Blueprint $table): void {
            $table->dropIndex('developer_key_project_environment_status');
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['revoked_at', 'disabled_at']);
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
