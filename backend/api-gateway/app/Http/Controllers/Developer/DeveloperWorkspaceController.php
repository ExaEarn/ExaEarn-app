<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperApiKey;
use App\Models\DeveloperApiRequestLog;
use App\Models\DeveloperOrganization;
use App\Models\DeveloperOrganizationInvitation;
use App\Models\DeveloperOrganizationMembership;
use App\Models\DeveloperProject;
use App\Models\DeveloperWebhookEndpoint;
use App\Models\DeveloperWorkspace;
use App\Notifications\DeveloperOrganizationInvitationNotification;
use App\Services\DeveloperApiKeyService;
use App\Services\DeveloperWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class DeveloperWorkspaceController extends Controller
{
    public function __construct(private readonly DeveloperWorkspaceService $workspaces, private readonly DeveloperApiKeyService $keys) {}

    public function overview(Request $request): JsonResponse
    {
        $user=$request->user(); $personal=$this->workspaces->ensurePersonalWorkspace($user); $workspaces=$this->workspaces->workspaces($user);
        $projectIds=DeveloperProject::query()->whereIn('workspace_id',$workspaces->pluck('id'))->pluck('id');
        $workspaceData=$workspaces->map(function(DeveloperWorkspace $workspace)use($user){$organization=DeveloperOrganization::query()->where('workspace_id',$workspace->id)->first();$membership=$organization?DeveloperOrganizationMembership::query()->where('organization_id',$organization->id)->where('user_id',$user->id)->where('status','active')->first():null;return ['id'=>$workspace->id,'workspace_uuid'=>$workspace->workspace_uuid,'type'=>$workspace->type,'name'=>$workspace->name,'status'=>$workspace->status,'organization_id'=>$organization?->id,'role'=>$membership?->role??($workspace->type==='personal'?'owner':null)];});
        return response()->json(['success'=>true,'data'=>[
            'workspaces'=>$workspaceData,'personal_workspace_id'=>$personal->id,
            'projects'=>DeveloperProject::query()->with('environments')->whereIn('id',$projectIds)->where('status','!=','archived')->latest()->get(),
            'counts'=>['api_keys'=>DeveloperApiKey::query()->whereIn('project_id',$projectIds)->count(),'webhooks'=>DeveloperWebhookEndpoint::query()->whereIn('project_id',$projectIds)->count(),'recent_requests'=>DeveloperApiRequestLog::query()->whereIn('project_id',$projectIds)->where('created_at','>=',now()->subDay())->count()],
        ]]);
    }

    public function createOrganization(Request $request): JsonResponse
    {
        $payload=$request->validate(['name'=>['required','string','max:140']]);
        $organization=$this->workspaces->createOrganization($request->user(),trim($payload['name']));
        $this->keys->audit($request->user()->id,null,null,'developer.organization.created','Developer organization created.',['organization_id'=>$organization->id]);
        return response()->json(['success'=>true,'data'=>$organization],201);
    }

    public function team(Request $request, int $organizationId): JsonResponse
    {
        $organization=DeveloperOrganization::query()->findOrFail($organizationId); $this->workspaces->assert($request->user(),$organization,'organization.members.read');
        $members=DeveloperOrganizationMembership::query()->with('user:id,name,email')->where('organization_id',$organization->id)->where('status','active')->get();
        $invites=DeveloperOrganizationInvitation::query()->where('organization_id',$organization->id)->where('status','pending')->get()->map(fn($invite)=>['id'=>$invite->id,'invitation_uuid'=>$invite->invitation_uuid,'email'=>Crypt::decryptString($invite->email_encrypted),'role'=>$invite->role,'status'=>$invite->status,'created_at'=>$invite->created_at,'expires_at'=>$invite->expires_at]);
        return response()->json(['success'=>true,'data'=>['organization'=>$organization,'members'=>$members,'invitations'=>$invites]]);
    }

    public function invite(Request $request, int $organizationId): JsonResponse
    {
        $organization=DeveloperOrganization::query()->findOrFail($organizationId); $payload=$request->validate(['email'=>['required','email'],'role'=>['required','in:admin,developer,viewer,ADMIN,DEVELOPER,VIEWER']]);
        try { $result=$this->workspaces->invite($request->user(),$organization,$payload['email'],$payload['role']); }
        catch(RuntimeException $e){ return response()->json(['success'=>false,'message'=>$e->getMessage()],422); }
        Notification::route('mail',strtolower($payload['email']))->notify(new DeveloperOrganizationInvitationNotification($organization->name,$result['token']));
        $this->keys->audit($request->user()->id,null,null,'developer.organization.invitation.created','Organization invitation created.',['organization_id'=>$organization->id,'invitation_id'=>$result['invitation']->id,'role'=>$result['invitation']->role]);
        $data=['invitation'=>$result['invitation']]; if(app()->environment('testing'))$data['token']=$result['token'];
        return response()->json(['success'=>true,'message'=>'If delivery is available, the invitation will be sent.','data'=>$data],201);
    }

    public function revokeInvitation(Request $request,int $organizationId,int $invitationId): JsonResponse
    {
        $organization=DeveloperOrganization::query()->findOrFail($organizationId); $this->workspaces->assert($request->user(),$organization,'organization.members.invite');
        $invite=DeveloperOrganizationInvitation::query()->where('organization_id',$organization->id)->findOrFail($invitationId); $invite->update(['status'=>'revoked','revoked_at'=>now()]);
        $this->keys->audit($request->user()->id,null,null,'developer.organization.invitation.revoked','Organization invitation revoked.',['organization_id'=>$organization->id,'invitation_id'=>$invite->id]);
        return response()->json(['success'=>true]);
    }

    public function acceptInvitation(Request $request): JsonResponse
    {
        $payload=$request->validate(['token'=>['required','string','min:40','max:200']]);
        try{$member=$this->workspaces->acceptInvitation($request->user(),$payload['token']);}catch(RuntimeException $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],422);}
        $this->keys->audit($request->user()->id,null,null,'developer.organization.invitation.accepted','Organization invitation accepted.',['organization_id'=>$member->organization_id]);
        return response()->json(['success'=>true,'data'=>$member],201);
    }

    public function changeRole(Request $request,int $organizationId,int $memberId): JsonResponse
    {
        $organization=DeveloperOrganization::query()->findOrFail($organizationId); $member=DeveloperOrganizationMembership::query()->where('organization_id',$organization->id)->findOrFail($memberId); $payload=$request->validate(['role'=>['required','in:admin,developer,viewer']]);
        try{$this->workspaces->changeRole($request->user(),$organization,$member,$payload['role']);}catch(RuntimeException $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],422);}
        $this->keys->audit($request->user()->id,null,null,'developer.organization.member.role_changed','Organization member role changed.',['organization_id'=>$organization->id,'member_id'=>$member->id,'role'=>$payload['role']]);
        return response()->json(['success'=>true,'data'=>$member->fresh()]);
    }

    public function removeMember(Request $request,int $organizationId,int $memberId): JsonResponse
    {
        $organization=DeveloperOrganization::query()->findOrFail($organizationId); $member=DeveloperOrganizationMembership::query()->where('organization_id',$organization->id)->findOrFail($memberId);
        try{$this->workspaces->removeMember($request->user(),$organization,$member);}catch(RuntimeException $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],422);}
        $this->keys->audit($request->user()->id,null,null,'developer.organization.member.removed','Organization member removed.',['organization_id'=>$organization->id,'member_id'=>$member->id]);
        return response()->json(['success'=>true]);
    }

    public function transferOwnership(Request $request,int $organizationId): JsonResponse
    {
        $organization=DeveloperOrganization::query()->findOrFail($organizationId); $payload=$request->validate(['member_id'=>['required','integer']]); $target=DeveloperOrganizationMembership::query()->where('organization_id',$organization->id)->findOrFail($payload['member_id']);
        try{$this->workspaces->transferOwnership($request->user(),$organization,$target);}catch(RuntimeException $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],422);}
        $this->keys->audit($request->user()->id,null,null,'developer.organization.ownership.transferred','Organization ownership transferred.',['organization_id'=>$organization->id,'new_owner_user_id'=>$target->user_id]);
        return response()->json(['success'=>true]);
    }

    public function createProject(Request $request): JsonResponse
    {
        $payload=$request->validate(['workspace_id'=>['required','integer'],'name'=>['required','string','max:120'],'description'=>['nullable','string','max:1000']]); $workspace=DeveloperWorkspace::query()->findOrFail($payload['workspace_id']);
        try{$project=$this->workspaces->provisionProject($request->user(),$workspace,$payload);}catch(RuntimeException $e){return response()->json(['success'=>false,'message'=>$e->getMessage()],422);}
        $this->keys->audit($request->user()->id,$project->id,null,'developer.project.created','Developer project created.',['workspace_id'=>$workspace->id]);
        return response()->json(['success'=>true,'data'=>$project],201);
    }

    public function archiveProject(Request $request,int $projectId): JsonResponse
    {
        $project=$this->workspaces->project($request->user(),$projectId,'project.archive'); if($project->status==='archived')return response()->json(['success'=>true,'data'=>$project]);
        $project->update(['status'=>'archived','archived_at'=>now()]); DeveloperApiKey::query()->where('project_id',$project->id)->where('status','active')->update(['status'=>'disabled']); DeveloperWebhookEndpoint::query()->where('project_id',$project->id)->where('status','active')->update(['status'=>'disabled']);
        $this->keys->audit($request->user()->id,$project->id,null,'developer.project.archived','Developer project archived.',[]);
        return response()->json(['success'=>true,'data'=>$project->fresh('environments')]);
    }
}
