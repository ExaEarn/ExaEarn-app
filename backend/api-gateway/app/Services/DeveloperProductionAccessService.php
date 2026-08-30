<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\DeveloperApiKey;
use App\Models\DeveloperApiRealtimeSession;
use App\Models\DeveloperProductionAccessRequest;
use App\Models\DeveloperProductionAccessReview;
use App\Models\DeveloperProductionCapability;
use App\Models\DeveloperProductionCapabilityReview;
use App\Models\DeveloperProject;
use App\Models\InstitutionalAccount;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DeveloperProductionAccessService
{
    private const ACTIVE_REQUESTS=['draft','submitted','under_review','action_required','partially_approved','approved','suspended'];
    private const DECISIONS=['approved','restricted','rejected','suspended','revoked'];
    private const REVIEWABLE=['submitted','under_review','action_required','partially_approved'];

    public function __construct(
        private readonly DeveloperWorkspaceService $workspaces,
        private readonly DeveloperApiScopeRegistry $scopes,
        private readonly CompliancePolicyService $compliance,
        private readonly ProductionAccessReviewerConflictService $reviewerConflicts,
    ) {}

    public function status(User $user, int $projectId): array
    {
        $project=$this->workspaces->project($user,$projectId,'project.read');
        $request=DeveloperProductionAccessRequest::query()->with(['capabilities','reviews'=>fn($q)=>$q->orderBy('id')])->where('project_id',$project->id)->latest('id')->first();
        return ['project_id'=>$project->id,'environment'=>$project->environments()->where('type','production')->first(),'request'=>$request,'eligible_scopes'=>$this->approvedCapabilities($project->id)];
    }

    public function submit(User $user, DeveloperProject $project, array $payload): DeveloperProductionAccessRequest
    {
        $project=$this->workspaces->project($user,$project->id,'production_access.request');
        $requested=$this->validateRequested((array)$payload['capabilities']);
        if($requested===[]) throw new RuntimeException('At least one Production capability is required.');
        $environment=$project->environments()->where('type','production')->firstOrFail();
        $applicant=$project->organization_id?'organization':'individual';
        if($applicant==='organization' && !config('developer_api.production_access.organization_enabled',false)) throw new RuntimeException('ORGANIZATION_PRODUCTION_ACCESS_BLOCKED');
        $jurisdiction=$this->jurisdiction($project,$user);
        $this->assertVerification($project,$user,$applicant);

        return DB::transaction(function() use($user,$project,$payload,$requested,$environment,$applicant,$jurisdiction): DeveloperProductionAccessRequest {
            $existing=DeveloperProductionAccessRequest::query()->where('project_id',$project->id)->where('idempotency_key',$payload['idempotency_key'])->lockForUpdate()->first();
            if($existing) return $existing->load('capabilities');
            if(DeveloperProductionAccessRequest::query()->where('project_id',$project->id)->whereIn('status',self::ACTIVE_REQUESTS)->lockForUpdate()->exists()) throw new RuntimeException('This project already has an active Production Access request.');
            $request=DeveloperProductionAccessRequest::query()->create([
                'request_uuid'=>(string)Str::uuid(),'project_id'=>$project->id,'environment_id'=>$environment->id,'submitted_by'=>$user->id,
                'applicant_type'=>$applicant,'use_case'=>$payload['use_case'],'status'=>'submitted','jurisdiction'=>$jurisdiction,
                'request_context'=>array_filter(['expected_request_volume'=>$payload['expected_request_volume']??null,'expected_trading_volume'=>$payload['expected_trading_volume']??null,'website'=>$payload['website']??null,'technical_contact'=>$payload['technical_contact']??null,'business_context'=>$payload['business_context']??null]),
                'idempotency_key'=>$payload['idempotency_key'],'submitted_at'=>now(),
            ]);
            foreach($requested as $scope){
                $definition=$this->scopes->all()[$scope];
                DeveloperProductionCapability::query()->create(['request_id'=>$request->id,'project_id'=>$project->id,'capability'=>$scope,'status'=>($definition['product_status']??'RESTRICTED')==='AVAILABLE'?'pending':'restricted','reason_code'=>($definition['product_status']??'RESTRICTED')==='AVAILABLE'?null:'PRODUCT_NOT_PUBLICLY_AVAILABLE']);
            }
            $this->review($request,'submitted','submitted',$user->id,null,'Production Access request submitted.',null,['capabilities'=>$requested],$payload['idempotency_key'].':event');
            return $request->load('capabilities');
        });
    }

    public function decide(Admin $admin, DeveloperProductionAccessRequest $request, array $payload): DeveloperProductionAccessRequest
    {
        $action=strtolower($payload['action']);
        $permission=in_array($action,['suspend','revoke'],true)?"developer.production.{$action}":($action==='reject'?'developer.production.reject':'developer.production.approve');
        if(!$admin->hasPermission($permission)) throw new AuthorizationException('Developer Production Access reviewer permission denied.');
        if(!in_array($action,['suspend','revoke'],true)) $this->reviewerConflicts->assertIndependent($admin,$request->loadMissing('project.organization'));
        return DB::transaction(function() use($admin,$request,$payload,$action): DeveloperProductionAccessRequest {
            $locked=DeveloperProductionAccessRequest::query()->with('capabilities')->lockForUpdate()->findOrFail($request->id);
            if(isset($payload['expected_version']) && (int)$payload['expected_version']!==(int)$locked->version) throw new RuntimeException('Production Access request changed. Refresh and retry.');
            if(DeveloperProductionAccessReview::query()->where('request_id',$locked->id)->where('idempotency_key',$payload['idempotency_key'])->exists()) return $locked;
            $from=$locked->status;
            $this->assertTransitionAllowed($from,$action);
            if($action==='resume'){
                $owner=$locked->project->organization_id?$locked->project->organization?->owner:$locked->project->user;
                if(!$owner) throw new RuntimeException('PRODUCTION_ACCESS_REQUIRED');
                $this->assertVerification($locked->project,$owner,$locked->applicant_type);
                $locked->status='under_review';
                $locked->capabilities()->where('status','suspended')->update(['status'=>'pending','reason_code'=>'RESUME_REVIEW_REQUIRED','approval_count'=>0,'decided_by'=>null,'decided_at'=>null]);
            } elseif($action==='action_required'){
                $locked->status='action_required';$locked->developer_message=$payload['public_message']??'Additional information is required.';
            } elseif(in_array($action,['suspend','revoke','reject'],true)){
                $target=$action==='reject'?'rejected':($action==='revoke'?'revoked':'suspended');
                $locked->status=$target;
                $locked->capabilities()->whereIn('status',['pending','pending_second_review','approved','restricted'])->update(['status'=>$target,'reason_code'=>strtoupper($target).'_BY_REVIEW','decided_by'=>$admin->id,'decided_at'=>now()]);
            } else {
                $decisions=(array)($payload['capabilities']??[]);
                foreach($decisions as $scope=>$decision){
                    $decision=strtolower((string)$decision);
                    if(!in_array($decision,self::DECISIONS,true)) throw new RuntimeException('Invalid capability decision.');
                    $capability=$locked->capabilities->firstWhere('capability',strtolower((string)$scope));
                    if(!$capability) throw new RuntimeException('Review cannot grant an unrequested capability.');
                    $definition=$this->scopes->all()[$capability->capability]??[];
                    if($decision==='approved' && ($definition['product_status']??'RESTRICTED')!=='AVAILABLE') $decision='restricted';
                    if($capability->capability==='wallet.withdraw' && $decision==='approved' && !$admin->hasPermission('developer.production.withdrawal_approve')) throw new AuthorizationException('Separate withdrawal approval permission is required.');
                    $required=in_array(strtoupper((string)($definition['risk']??'READ')),['HIGH','RESTRICTED'],true)?2:1;
                    if($decision==='approved' && $required===2){
                        if(!$admin->canonical_user_id) throw new AuthorizationException('Reviewer canonical identity is required.');
                        if(DeveloperProductionCapabilityReview::query()->where('capability_id',$capability->id)->where('canonical_user_id',$admin->canonical_user_id)->where('decision','approved')->exists()) throw new AuthorizationException('A distinct second reviewer is required.');
                        $count=DeveloperProductionCapabilityReview::query()->where('capability_id',$capability->id)->where('decision','approved')->lockForUpdate()->count()+1;
                        DeveloperProductionCapabilityReview::query()->create(['review_uuid'=>(string)Str::uuid(),'request_id'=>$locked->id,'capability_id'=>$capability->id,'admin_id'=>$admin->id,'canonical_user_id'=>$admin->canonical_user_id,'decision'=>'approved','review_sequence'=>$count,'reason_code'=>'REVIEW_APPROVED','internal_note'=>$payload['internal_note']??null,'policy_version'=>(string)config('compliance.policy_version'),'idempotency_key'=>$payload['idempotency_key'].':'.$capability->capability]);
                        $final=$count>=$required?'approved':'pending_second_review';
                        $capability->update(['status'=>$final,'required_approvals'=>$required,'approval_count'=>$count,'reason_code'=>$final==='approved'?'DUAL_REVIEW_APPROVED':'SECOND_REVIEW_REQUIRED','limits'=>$payload['limits'][$scope]??$capability->limits,'decided_by'=>$final==='approved'?$admin->id:null,'decided_at'=>$final==='approved'?now():null]);
                    } else {
                        if($admin->canonical_user_id) DeveloperProductionCapabilityReview::query()->create(['review_uuid'=>(string)Str::uuid(),'request_id'=>$locked->id,'capability_id'=>$capability->id,'admin_id'=>$admin->id,'canonical_user_id'=>$admin->canonical_user_id,'decision'=>$decision,'review_sequence'=>1,'reason_code'=>$decision==='approved'?'REVIEW_APPROVED':strtoupper($decision).'_BY_REVIEW','internal_note'=>$payload['internal_note']??null,'policy_version'=>(string)config('compliance.policy_version'),'idempotency_key'=>$payload['idempotency_key'].':'.$capability->capability]);
                        $capability->update(['status'=>$decision,'required_approvals'=>$required,'approval_count'=>$decision==='approved'?1:0,'reason_code'=>$decision==='approved'?'REVIEW_APPROVED':strtoupper($decision).'_BY_REVIEW','limits'=>$payload['limits'][$scope]??null,'decided_by'=>$admin->id,'decided_at'=>now()]);
                    }
                }
                $statuses=$locked->capabilities()->pluck('status');
                $locked->status=$statuses->every(fn($s)=>$s==='approved')?'approved':($statuses->contains('approved')||$statuses->contains('pending_second_review')?'partially_approved':'rejected');
                if($statuses->contains('approved')) $locked->environment_id && $locked->project->environments()->where('type','production')->update(['status'=>'active','activated_at'=>now()]);
                $locked->decided_at=now();
            }
            if(in_array($locked->status,['suspended','revoked','rejected','under_review'],true)) $locked->project->environments()->where('type','production')->update(['status'=>'locked']);
            $locked->version++;$locked->save();
            $this->review($locked,$action,$locked->status,null,$admin->id,$payload['public_message']??null,$payload['internal_note']??null,['capabilities'=>$payload['capabilities']??[]],$payload['idempotency_key'],$from);
            $this->invalidate($locked->project_id);
            return $locked->fresh(['capabilities','reviews']);
        });
    }

    public function approvedCapabilities(int $projectId): array
    {
        return Cache::remember("developer-production-capabilities:{$projectId}",60,fn()=>DeveloperProductionCapability::query()->where('project_id',$projectId)->where('status','approved')->pluck('capability')->unique()->values()->all());
    }

    public function assertCapabilities(DeveloperProject $project, array $capabilities): void
    {
        if($project->organization_id && !config('developer_api.production_access.organization_enabled',false)) throw new RuntimeException('ORGANIZATION_PRODUCTION_ACCESS_BLOCKED');
        $environment=$project->environments->firstWhere('type','production');
        if(!$environment || $environment->status!=='active') throw new RuntimeException('PRODUCTION_ACCESS_REQUIRED');
        $approved=$this->approvedCapabilities($project->id);
        foreach($capabilities as $scope) if(!in_array($scope,$approved,true)) throw new RuntimeException('CAPABILITY_NOT_APPROVED');
        $owner=$project->organization_id?$project->organization?->owner:$project->user;
        if(!$owner) throw new RuntimeException('PRODUCTION_ACCESS_REQUIRED');
        $institution=null;
        if($project->organization_id){
            $organization=$project->organization;
            $institution=$organization?->institution_id?InstitutionalAccount::query()->find($organization->institution_id):null;
            if(!$institution || $institution->kyb_status!=='APPROVED' || $institution->status!=='ACTIVE' || $organization->authorized_representative_status!=='verified') {
                $this->invalidate($project->id);
                throw new RuntimeException('VERIFICATION_REQUIRED');
            }
        }
        $this->assertRuntimePolicy($owner,$project,$capabilities,$institution);
    }

    public function invalidate(int $projectId): void
    {
        Cache::forget("developer-production-capabilities:{$projectId}");
        DeveloperApiRealtimeSession::query()->where('project_id',$projectId)->where('environment','production')->where('status','active')->update(['status'=>'revoked','revoked_at'=>now()]);
    }

    private function validateRequested(array $requested): array
    {
        $values=array_values(array_unique(array_map(fn($v)=>strtolower((string)$v),$requested)));
        foreach($values as $scope) if(!isset($this->scopes->all()[$scope]) || !in_array('production',$this->scopes->all()[$scope]['environments']??[],true)) throw new RuntimeException("Unsupported Production capability: {$scope}");
        return $values;
    }

    private function assertTransitionAllowed(string $from,string $action): void
    {
        if($action==='resume') {
            if($from!=='suspended') throw new RuntimeException('INVALID_PRODUCTION_ACCESS_TRANSITION');
            return;
        }
        if($action==='revoke') {
            if(in_array($from,['revoked','rejected','expired'],true)) throw new RuntimeException('INVALID_PRODUCTION_ACCESS_TRANSITION');
            return;
        }
        if($action==='suspend') {
            if(!in_array($from,['submitted','under_review','action_required','partially_approved','approved'],true)) throw new RuntimeException('INVALID_PRODUCTION_ACCESS_TRANSITION');
            return;
        }
        if(!in_array($from,self::REVIEWABLE,true)) throw new RuntimeException('INVALID_PRODUCTION_ACCESS_TRANSITION');
    }

    private function assertVerification(DeveloperProject $project,User $user,string $type): void
    {
        if($type==='individual'){
            if((int)$user->kyc_level<1 || strtoupper((string)$user->account_status)==='SUSPENDED') throw new RuntimeException('VERIFICATION_REQUIRED');
            $maxAge=(int)config('developer_api.production_access.kyc_max_age_days',0);
            if($maxAge>0 && (!$user->kyc_verified_at || $user->kyc_verified_at->lt(now()->subDays($maxAge)))) throw new RuntimeException('VERIFICATION_REQUIRED');
            return;
        }
        $organization=$project->organization;
        $institution=$organization?->institution_id?InstitutionalAccount::query()->find($organization->institution_id):null;
        if(!$institution || $institution->kyb_status!=='APPROVED' || $institution->status!=='ACTIVE') throw new RuntimeException('VERIFICATION_REQUIRED');
        if($organization->authorized_representative_status!=='verified') throw new RuntimeException('AUTHORIZED_REPRESENTATIVE_REQUIRED');
    }

    private function jurisdiction(DeveloperProject $project,User $user): ?string
    {
        if($project->organization_id){
            $id=$project->organization?->institution_id;
            return $id?strtoupper((string)InstitutionalAccount::query()->find($id)?->country_of_incorporation):null;
        }
        return strtoupper((string)($user->verified_country?:$user->residence_country))?:null;
    }

    private function assertRuntimePolicy(User $owner,DeveloperProject $project,array $scopes,?InstitutionalAccount $institution=null): void
    {
        foreach($scopes as $scope){
            $product=strtoupper(explode('.',$scope)[0]);
            if($product==='ACCOUNT' || $product==='MARKET' || $product==='WEBHOOKS') continue;
            $decision=$this->compliance->decide($institution ? null : $owner,$product,['action'=>str_contains($scope,'read')?'READ':'USE','log'=>true,'institution'=>$institution,'institution_id'=>$institution?->id,'account_type'=>$institution?'INSTITUTIONAL':'INDIVIDUAL','jurisdiction'=>$institution?->country_of_incorporation,'actor_type'=>'developer_api','actor_id'=>$owner->id]);
            if(!in_array($decision['decision'],[CompliancePolicyService::ALLOW,'RESTRICT'],true)) throw new RuntimeException($decision['decision']==='REQUIRE_KYC'?'VERIFICATION_REQUIRED':'JURISDICTION_RESTRICTED');
        }
    }

    private function review(DeveloperProductionAccessRequest $request,string $action,string $to,?int $userId,?int $adminId,?string $public,?string $internal,array $context,?string $idempotency,?string $from=null): void
    {
        DeveloperProductionAccessReview::query()->create(['event_uuid'=>(string)Str::uuid(),'request_id'=>$request->id,'actor_user_id'=>$userId,'actor_admin_id'=>$adminId,'action'=>$action,'from_status'=>$from,'to_status'=>$to,'public_message'=>$public,'internal_note'=>$internal,'context'=>$context,'idempotency_key'=>$idempotency]);
    }
}
