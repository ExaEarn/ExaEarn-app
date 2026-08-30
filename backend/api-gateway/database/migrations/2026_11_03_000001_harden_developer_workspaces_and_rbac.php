<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('developer_workspaces', function (Blueprint $table): void {
            $table->id();
            $table->uuid('workspace_uuid')->unique();
            $table->string('type', 20)->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('name', 140);
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->unique(['type', 'owner_user_id']);
        });

        Schema::table('developer_organizations', function (Blueprint $table): void {
            $table->foreignId('workspace_id')->nullable()->after('id')->constrained('developer_workspaces')->restrictOnDelete();
            $table->string('slug', 160)->nullable()->unique()->after('name');
            $table->string('verification_status', 30)->default('unverified')->after('status');
            $table->foreignId('created_by')->nullable()->after('owner_user_id')->constrained('users')->nullOnDelete();
        });
        Schema::table('developer_organization_memberships', function (Blueprint $table): void {
            $table->foreignId('invited_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable()->after('status');
        });
        Schema::table('developer_projects', function (Blueprint $table): void {
            $table->foreignId('workspace_id')->nullable()->after('user_id')->constrained('developer_workspaces')->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->after('workspace_id')->constrained('developer_organizations')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->after('organization_id')->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('status');
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('developer_project_environments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('developer_projects')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('status', 30)->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'type']);
        });
        Schema::create('developer_organization_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('invitation_uuid')->unique();
            $table->foreignId('organization_id')->constrained('developer_organizations')->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->string('email_hash', 64);
            $table->string('email_encrypted');
            $table->string('role', 30);
            $table->string('token_hash', 64)->unique();
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'email_hash', 'status']);
        });
        Schema::table('developer_webhook_endpoints', function (Blueprint $table): void {
            $table->string('environment', 20)->default('sandbox')->after('project_id')->index();
        });

        foreach (DB::table('users')->whereIn('id', DB::table('developer_profiles')->pluck('user_id'))->get(['id', 'name']) as $user) {
            $workspaceId = DB::table('developer_workspaces')->insertGetId([
                'workspace_uuid' => (string) Str::uuid(), 'type' => 'personal', 'owner_user_id' => $user->id,
                'name' => $user->name.' Workspace', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('developer_projects')->where('user_id', $user->id)->whereNull('workspace_id')->update(['workspace_id' => $workspaceId, 'created_by' => $user->id]);
        }
        foreach (DB::table('developer_organizations')->get() as $organization) {
            $workspaceId = DB::table('developer_workspaces')->insertGetId([
                'workspace_uuid' => (string) Str::uuid(), 'type' => 'organization', 'owner_user_id' => null,
                'name' => $organization->name, 'status' => $organization->status, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('developer_organizations')->where('id', $organization->id)->update([
                'workspace_id' => $workspaceId, 'slug' => Str::slug($organization->name).'-'.Str::lower(Str::random(6)),
                'created_by' => $organization->owner_user_id,
            ]);
            DB::table('developer_organization_memberships')->where('organization_id', $organization->id)->whereNull('joined_at')->update(['joined_at' => now()]);
        }
        foreach (DB::table('developer_projects')->get(['id', 'environment']) as $project) {
            DB::table('developer_project_environments')->insert([
                ['project_id' => $project->id, 'type' => 'sandbox', 'status' => 'active', 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                ['project_id' => $project->id, 'type' => 'production', 'status' => 'not_activated', 'activated_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('developer_webhook_endpoints', fn (Blueprint $table) => $table->dropColumn('environment'));
        Schema::dropIfExists('developer_organization_invitations');
        Schema::dropIfExists('developer_project_environments');
        Schema::table('developer_projects', function (Blueprint $table): void { $table->dropConstrainedForeignId('workspace_id'); $table->dropConstrainedForeignId('organization_id'); $table->dropConstrainedForeignId('created_by'); $table->dropColumn('archived_at'); });
        Schema::table('developer_organization_memberships', function (Blueprint $table): void { $table->dropConstrainedForeignId('invited_by'); $table->dropColumn('joined_at'); });
        Schema::table('developer_organizations', function (Blueprint $table): void { $table->dropConstrainedForeignId('workspace_id'); $table->dropConstrainedForeignId('created_by'); $table->dropColumn(['slug', 'verification_status']); });
        Schema::dropIfExists('developer_workspaces');
    }
};
