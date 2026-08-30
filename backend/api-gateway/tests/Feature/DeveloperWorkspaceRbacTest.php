<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DeveloperApiKey;
use App\Models\DeveloperOrganization;
use App\Models\DeveloperOrganizationInvitation;
use App\Models\DeveloperOrganizationMembership;
use App\Models\DeveloperProfile;
use App\Models\DeveloperProject;
use App\Models\DeveloperProjectEnvironment;
use App\Models\DeveloperWorkspace;
use App\Models\User;
use App\Services\DeveloperApiKeyService;
use App\Services\DeveloperWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeveloperWorkspaceRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_and_personal_workspace_are_unique_and_retry_safe(): void
    {
        $user=User::factory()->create();
        DeveloperProfile::query()->firstOrCreate(['user_id'=>$user->id]);
        DeveloperProfile::query()->firstOrCreate(['user_id'=>$user->id]);
        $first=app(DeveloperWorkspaceService::class)->ensurePersonalWorkspace($user);
        $second=app(DeveloperWorkspaceService::class)->ensurePersonalWorkspace($user);
        $this->assertSame($first->id,$second->id);
        $this->assertSame(1,DeveloperProfile::query()->where('user_id',$user->id)->count());
        $this->assertSame(1,DeveloperWorkspace::query()->where('owner_user_id',$user->id)->where('type','personal')->count());
    }

    public function test_organization_creation_is_transactional_and_owner_membership_is_authoritative(): void
    {
        $owner=User::factory()->create();
        $response=$this->actingAs($owner)->postJson('/api/developer/organizations',['name'=>'Exa Labs'])->assertCreated();
        $organization=DeveloperOrganization::query()->firstOrFail();
        $this->assertDatabaseHas('developer_organization_memberships',['organization_id'=>$organization->id,'user_id'=>$owner->id,'role'=>'owner','status'=>'active']);
        $this->assertNotNull($organization->workspace_id);
        $response->assertJsonPath('data.production_access_status','not_activated');
    }

    public function test_role_matrix_denies_viewer_mutation_and_protects_owner_operations(): void
    {
        [$organization,$owner]=$this->organization();
        $viewer=User::factory()->create(); $developer=User::factory()->create(); $admin=User::factory()->create();
        foreach([[$viewer,'viewer'],[$developer,'developer'],[$admin,'admin']] as [$user,$role]) DeveloperOrganizationMembership::query()->create(['organization_id'=>$organization->id,'user_id'=>$user->id,'role'=>$role,'status'=>'active','joined_at'=>now()]);
        $this->actingAs($viewer)->postJson('/api/developer/workspace-projects',['workspace_id'=>$organization->workspace_id,'name'=>'Denied'])->assertForbidden();
        $this->actingAs($developer)->postJson("/api/developer/organizations/{$organization->id}/invitations",['email'=>'new@example.com','role'=>'viewer'])->assertForbidden();
        $target=DeveloperOrganizationMembership::query()->where('user_id',$viewer->id)->firstOrFail();
        $this->actingAs($admin)->withSession(['auth_recent_at'=>time()])->postJson("/api/developer/organizations/{$organization->id}/ownership-transfer",['member_id'=>$target->id])->assertForbidden();
    }

    public function test_invitation_is_recipient_bound_expiring_revocable_and_one_time(): void
    {
        Notification::fake(); [$organization,$owner]=$this->organization();
        $invited=User::factory()->create(['email'=>'invitee@example.com']); $wrong=User::factory()->create(['email'=>'wrong@example.com']);
        $token=$this->actingAs($owner)->postJson("/api/developer/organizations/{$organization->id}/invitations",['email'=>$invited->email,'role'=>'developer'])->assertCreated()->json('data.token');
        $this->actingAs($wrong)->postJson('/api/developer/invitations/accept',['token'=>$token])->assertForbidden();
        $this->actingAs($invited)->postJson('/api/developer/invitations/accept',['token'=>$token])->assertCreated();
        $this->actingAs($invited)->postJson('/api/developer/invitations/accept',['token'=>$token])->assertStatus(422);
        $this->assertSame(1,DeveloperOrganizationMembership::query()->where('organization_id',$organization->id)->where('user_id',$invited->id)->count());

        $expired=app(DeveloperWorkspaceService::class)->invite($owner,$organization,'expired@example.com','viewer');
        $expired['invitation']->update(['expires_at'=>now()->subMinute()]);
        $expiredUser=User::factory()->create(['email'=>'expired@example.com']);
        $this->actingAs($expiredUser)->postJson('/api/developer/invitations/accept',['token'=>$expired['token']])->assertStatus(422);

        $revoked=app(DeveloperWorkspaceService::class)->invite($owner,$organization,'revoked@example.com','viewer');
        $this->actingAs($owner)->deleteJson("/api/developer/organizations/{$organization->id}/invitations/{$revoked['invitation']->id}")->assertOk();
        $revokedUser=User::factory()->create(['email'=>'revoked@example.com']);
        $this->actingAs($revokedUser)->postJson('/api/developer/invitations/accept',['token'=>$revoked['token']])->assertStatus(422);
    }

    public function test_final_owner_cannot_be_removed_or_demoted(): void
    {
        [$organization,$owner]=$this->organization(); $membership=DeveloperOrganizationMembership::query()->where('user_id',$owner->id)->firstOrFail();
        $this->actingAs($owner)->deleteJson("/api/developer/organizations/{$organization->id}/members/{$membership->id}")->assertStatus(422)->assertJsonPath('message','The final owner cannot be removed.');
        $this->actingAs($owner)->patchJson("/api/developer/organizations/{$organization->id}/members/{$membership->id}/role",['role'=>'admin'])->assertStatus(422)->assertJsonPath('message','The final owner cannot be demoted.');
    }

    public function test_ownership_transfer_requires_recent_auth_and_changes_authority_atomically(): void
    {
        [$organization,$owner]=$this->organization(); $next=User::factory()->create();
        $target=DeveloperOrganizationMembership::query()->create(['organization_id'=>$organization->id,'user_id'=>$next->id,'role'=>'admin','status'=>'active','joined_at'=>now()]);
        $url="/api/developer/organizations/{$organization->id}/ownership-transfer";
        $this->actingAs($owner)->postJson($url,['member_id'=>$target->id])->assertStatus(428);
        $this->actingAs($owner)->withSession(['auth_recent_at'=>time()])->postJson($url,['member_id'=>$target->id])->assertOk();
        $this->assertDatabaseHas('developer_organization_memberships',['id'=>$target->id,'role'=>'owner']);
        $this->assertDatabaseHas('developer_organizations',['id'=>$organization->id,'owner_user_id'=>$next->id]);
    }

    public function test_project_provisions_both_environments_and_production_remains_locked(): void
    {
        $user=User::factory()->create(); $workspace=app(DeveloperWorkspaceService::class)->ensurePersonalWorkspace($user);
        $project=$this->actingAs($user)->postJson('/api/developer/workspace-projects',['workspace_id'=>$workspace->id,'name'=>'Trading App'])->assertCreated()->json('data');
        $this->assertDatabaseHas('developer_project_environments',['project_id'=>$project['id'],'type'=>'sandbox','status'=>'active']);
        $this->assertDatabaseHas('developer_project_environments',['project_id'=>$project['id'],'type'=>'production','status'=>'not_activated']);
        $model=DeveloperProject::query()->findOrFail($project['id']);
        $this->expectException(\RuntimeException::class);
        app(DeveloperApiKeyService::class)->createKey($user->id,$model,['name'=>'Production','environment'=>'production','permissions'=>['account.read']]);
    }

    public function test_project_idor_archival_and_membership_removal_fail_closed(): void
    {
        [$organization,$owner]=$this->organization(); $member=User::factory()->create();
        $membership=DeveloperOrganizationMembership::query()->create(['organization_id'=>$organization->id,'user_id'=>$member->id,'role'=>'developer','status'=>'active','joined_at'=>now()]);
        $project=app(DeveloperWorkspaceService::class)->provisionProject($owner,DeveloperWorkspace::query()->findOrFail($organization->workspace_id),['name'=>'Org API']);
        $outsider=User::factory()->create();
        $this->actingAs($outsider)->postJson("/api/developer/projects/{$project->id}/archive")->assertForbidden();
        $this->actingAs($member)->getJson("/api/developer/projects/{$project->id}/webhooks")->assertOk();
        $this->actingAs($owner)->deleteJson("/api/developer/organizations/{$organization->id}/members/{$membership->id}")->assertOk();
        $this->actingAs($member)->getJson("/api/developer/projects/{$project->id}/webhooks")->assertForbidden();
        $this->actingAs($owner)->postJson("/api/developer/projects/{$project->id}/archive")->assertOk();
        $this->assertDatabaseHas('developer_projects',['id'=>$project->id,'status'=>'archived']);
    }

    private function organization(): array
    {
        $owner=User::factory()->create(); $organization=app(DeveloperWorkspaceService::class)->createOrganization($owner,'Workspace '.uniqid()); return[$organization,$owner];
    }
}
