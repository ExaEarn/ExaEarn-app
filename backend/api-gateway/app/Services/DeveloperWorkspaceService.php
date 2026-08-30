<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeveloperOrganization;
use App\Models\DeveloperOrganizationInvitation;
use App\Models\DeveloperOrganizationMembership;
use App\Models\DeveloperProject;
use App\Models\DeveloperProjectEnvironment;
use App\Models\DeveloperWorkspace;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DeveloperWorkspaceService
{
    public const PERMISSIONS = [
        'owner' => ['organization.read','organization.update','organization.members.read','organization.members.invite','organization.members.update','organization.members.remove','organization.ownership.transfer','project.read','project.create','project.update','project.archive','environment.read','environment.manage','api_key.read','api_key.create','api_key.update','api_key.revoke','webhook.read','webhook.create','webhook.update','webhook.delete','logs.read','usage.read','production_access.read','production_access.request'],
        'admin' => ['organization.read','organization.update','organization.members.read','organization.members.invite','organization.members.update','organization.members.remove','project.read','project.create','project.update','project.archive','environment.read','environment.manage','api_key.read','api_key.create','api_key.update','api_key.revoke','webhook.read','webhook.create','webhook.update','webhook.delete','logs.read','usage.read','production_access.read','production_access.request'],
        'developer' => ['organization.read','organization.members.read','project.read','project.create','project.update','environment.read','api_key.read','api_key.create','api_key.update','api_key.revoke','webhook.read','webhook.create','webhook.update','webhook.delete','logs.read','usage.read','production_access.read'],
        'viewer' => ['organization.read','organization.members.read','project.read','environment.read','api_key.read','webhook.read','logs.read','usage.read','production_access.read'],
    ];

    public function ensurePersonalWorkspace(User $user): DeveloperWorkspace
    {
        return DeveloperWorkspace::query()->firstOrCreate(
            ['type' => 'personal', 'owner_user_id' => $user->id],
            ['workspace_uuid' => (string) Str::uuid(), 'name' => $user->name.' Workspace', 'status' => 'active'],
        );
    }

    public function workspaces(User $user)
    {
        $personal = DeveloperWorkspace::query()->where('type', 'personal')->where('owner_user_id', $user->id);
        $organizationIds = DeveloperOrganizationMembership::query()->where('user_id', $user->id)->where('status', 'active')->pluck('organization_id');
        $organizationWorkspaceIds = DeveloperOrganization::query()->whereIn('id', $organizationIds)->where('status', 'active')->pluck('workspace_id');
        return DeveloperWorkspace::query()->where(fn ($query) => $query->whereIn('id', $personal->pluck('id'))->orWhereIn('id', $organizationWorkspaceIds))->where('status', 'active')->get();
    }

    public function createOrganization(User $actor, string $name): DeveloperOrganization
    {
        return DB::transaction(function () use ($actor, $name): DeveloperOrganization {
            $workspace = DeveloperWorkspace::query()->create(['workspace_uuid'=>(string) Str::uuid(),'type'=>'organization','owner_user_id'=>null,'name'=>$name,'status'=>'active']);
            $organization = DeveloperOrganization::query()->create(['organization_uuid'=>(string) Str::uuid(),'workspace_id'=>$workspace->id,'owner_user_id'=>$actor->id,'created_by'=>$actor->id,'name'=>$name,'slug'=>Str::slug($name).'-'.Str::lower(Str::random(6)),'status'=>'active','verification_status'=>'unverified','production_access_status'=>'not_activated']);
            DeveloperOrganizationMembership::query()->create(['organization_id'=>$organization->id,'user_id'=>$actor->id,'role'=>'owner','status'=>'active','joined_at'=>now()]);
            return $organization;
        });
    }

    public function membership(User $user, DeveloperOrganization $organization): DeveloperOrganizationMembership
    {
        if ($organization->status !== 'active') throw new AuthorizationException('Organization access is unavailable.');
        return DeveloperOrganizationMembership::query()->where('organization_id',$organization->id)->where('user_id',$user->id)->where('status','active')->firstOr(function () { throw new AuthorizationException('Organization access denied.'); });
    }

    public function assert(User $user, DeveloperOrganization $organization, string $permission): DeveloperOrganizationMembership
    {
        $membership = $this->membership($user, $organization);
        if (!in_array($permission, self::PERMISSIONS[strtolower($membership->role)] ?? [], true)) throw new AuthorizationException('Organization permission denied.');
        return $membership;
    }

    public function project(User $user, int $projectId, string $permission = 'project.read'): DeveloperProject
    {
        $project = DeveloperProject::query()->with('organization')->findOrFail($projectId);
        if ($project->status === 'suspended' || $project->workspace?->status === 'suspended') throw new AuthorizationException('Project access is suspended.');
        if ($project->organization_id) $this->assert($user, $project->organization, $permission);
        elseif ((int) $project->user_id !== (int) $user->id) throw new AuthorizationException('Project access denied.');
        return $project;
    }

    public function provisionProject(User $actor, DeveloperWorkspace $workspace, array $payload): DeveloperProject
    {
        $organization = DeveloperOrganization::query()->where('workspace_id', $workspace->id)->first();
        if ($workspace->type === 'personal') {
            if ((int) $workspace->owner_user_id !== (int) $actor->id) throw new AuthorizationException('Workspace access denied.');
            $limit = (int) config('developer_api.max_personal_projects', 5);
        } else {
            if (!$organization) throw new RuntimeException('Organization workspace is invalid.');
            $this->assert($actor, $organization, 'project.create');
            $limit = (int) config('developer_api.max_organization_projects', 20);
        }
        if (DeveloperProject::query()->where('workspace_id',$workspace->id)->where('status','!=','archived')->count() >= $limit) throw new RuntimeException('Project limit reached for this workspace.');
        return DB::transaction(function () use ($actor,$workspace,$organization,$payload): DeveloperProject {
            $project = DeveloperProject::query()->create(['project_uuid'=>(string) Str::uuid(),'user_id'=>$actor->id,'workspace_id'=>$workspace->id,'organization_id'=>$organization?->id,'created_by'=>$actor->id,'name'=>trim((string)$payload['name']),'description'=>$payload['description']??null,'environment'=>'sandbox','status'=>'active','tier'=>'standard','settings'=>[]]);
            DeveloperProjectEnvironment::query()->insert([
                ['project_id'=>$project->id,'type'=>'sandbox','status'=>'active','activated_at'=>now(),'created_at'=>now(),'updated_at'=>now()],
                ['project_id'=>$project->id,'type'=>'production','status'=>'not_activated','activated_at'=>null,'created_at'=>now(),'updated_at'=>now()],
            ]);
            return $project->load('environments');
        });
    }

    public function invite(User $actor, DeveloperOrganization $organization, string $email, string $role): array
    {
        $this->assert($actor,$organization,'organization.members.invite');
        $role = strtolower($role);
        if (!in_array($role,['admin','developer','viewer'],true)) throw new RuntimeException('This role cannot be invited.');
        $normalized = strtolower(trim($email)); $hash = hash('sha256',$normalized); $plain = Str::random(64);
        return DB::transaction(function () use ($actor,$organization,$role,$normalized,$hash,$plain): array {
            DeveloperOrganizationInvitation::query()->where('organization_id',$organization->id)->where('email_hash',$hash)->where('status','pending')->update(['status'=>'revoked','revoked_at'=>now()]);
            $invite = DeveloperOrganizationInvitation::query()->create(['invitation_uuid'=>(string) Str::uuid(),'organization_id'=>$organization->id,'invited_by'=>$actor->id,'email_hash'=>$hash,'email_encrypted'=>Crypt::encryptString($normalized),'role'=>$role,'token_hash'=>hash('sha256',$plain),'status'=>'pending','expires_at'=>now()->addDays(7)]);
            return ['invitation'=>$invite,'token'=>$plain];
        });
    }

    public function acceptInvitation(User $user, string $token): DeveloperOrganizationMembership
    {
        return DB::transaction(function () use ($user,$token): DeveloperOrganizationMembership {
            $invite = DeveloperOrganizationInvitation::query()->where('token_hash',hash('sha256',$token))->lockForUpdate()->firstOrFail();
            if ($invite->status !== 'pending' || $invite->expires_at->isPast()) throw new RuntimeException('Invitation is invalid or expired.');
            if (!hash_equals($invite->email_hash,hash('sha256',strtolower((string)$user->email)))) throw new AuthorizationException('Invitation recipient does not match this account.');
            $membership = DeveloperOrganizationMembership::query()->updateOrCreate(
                ['organization_id'=>$invite->organization_id,'user_id'=>$user->id],
                ['invited_by'=>$invite->invited_by,'role'=>$invite->role,'status'=>'active','joined_at'=>now()],
            );
            $invite->update(['status'=>'accepted','accepted_at'=>now()]);
            return $membership;
        });
    }

    public function changeRole(User $actor, DeveloperOrganization $organization, DeveloperOrganizationMembership $member, string $role): void
    {
        $this->assert($actor,$organization,'organization.members.update'); $role=strtolower($role);
        if (!in_array($role,['admin','developer','viewer'],true)) throw new RuntimeException('Use ownership transfer to assign an owner.');
        if ($member->role === 'owner' && $this->ownerCount($organization) <= 1) throw new RuntimeException('The final owner cannot be demoted.');
        $member->update(['role'=>$role]);
    }

    public function removeMember(User $actor, DeveloperOrganization $organization, DeveloperOrganizationMembership $member): void
    {
        $this->assert($actor,$organization,'organization.members.remove');
        if ($member->role === 'owner' && $this->ownerCount($organization) <= 1) throw new RuntimeException('The final owner cannot be removed.');
        $member->update(['status'=>'removed']);
    }

    public function transferOwnership(User $actor, DeveloperOrganization $organization, DeveloperOrganizationMembership $target): void
    {
        $current = $this->assert($actor,$organization,'organization.ownership.transfer');
        if ($target->organization_id !== $organization->id || $target->status !== 'active') throw new RuntimeException('Target must be an active organization member.');
        if ($current->id === $target->id) throw new RuntimeException('This member already owns the organization.');
        DB::transaction(function () use ($organization,$current,$target): void {
            $members = DeveloperOrganizationMembership::query()
                ->whereIn('id',[$current->id,$target->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $lockedCurrent = $members->get($current->id);
            $lockedTarget = $members->get($target->id);
            if (!$lockedCurrent || !$lockedTarget || $lockedCurrent->role !== 'owner' || $lockedTarget->status !== 'active') throw new RuntimeException('Organization ownership changed. Refresh and try again.');
            $lockedTarget->update(['role'=>'owner']);
            $lockedCurrent->update(['role'=>'admin']);
            DeveloperOrganization::query()->whereKey($organization->id)->update(['owner_user_id'=>$lockedTarget->user_id]);
        });
    }

    private function ownerCount(DeveloperOrganization $organization): int { return DeveloperOrganizationMembership::query()->where('organization_id',$organization->id)->where('status','active')->where('role','owner')->lockForUpdate()->count(); }
}
