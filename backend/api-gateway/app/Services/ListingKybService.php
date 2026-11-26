<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ListingAuditLog;
use App\Models\ListingOrganization;
use App\Models\ListingOrganizationDeclaration;
use App\Models\ListingOrganizationDocument;
use App\Models\ListingOrganizationInvitation;
use App\Models\ListingOrganizationPerson;
use App\Models\ListingTeamMember;
use App\Models\User;
use App\Notifications\ListingOrganizationInvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class ListingKybService
{
    public const STATES = ['DRAFT','READY_FOR_SUBMISSION','SUBMITTED','UNDER_REVIEW','INFORMATION_REQUESTED','ENHANCED_DUE_DILIGENCE','APPROVED','REJECTED','RESTRICTED','EXPIRED','REVERIFICATION_REQUIRED'];
    public const SCREENING_STATES = ['NOT_CHECKED','PENDING','CLEAR','MATCH_REVIEW_REQUIRED','BLOCKED','PROVIDER_ERROR','NOT_CONNECTED'];

    public function __construct(private readonly ListingLifecycleService $listing) {}

    public function dashboard(User $user, ListingOrganization $organization): array
    {
        $this->listing->assertOrganizationAccess($user, $organization);
        $organization->load(['people','kybDocuments','declarations','teamMembers','invitations','kybReviews']);
        $progress = $this->progress($organization);
        if ($organization->kyb_progress !== $progress['overall']) $organization->forceFill(['kyb_progress'=>$progress['overall'],'kyb_current_stage'=>$progress['current_stage']])->save();
        return ['organization'=>$organization->fresh(['people','kybDocuments','declarations','teamMembers','invitations','kybReviews']),'progress'=>$progress,'screening_provider'=>config('listing.screening_provider_connected')?'CONNECTED':'NOT_CONNECTED','registry_provider'=>config('listing.registry_provider_connected')?'CONNECTED':'NOT_CONNECTED','required_documents'=>config('listing.required_kyb_documents'),'declaration_version'=>config('listing.kyb_declaration_version')];
    }

    public function updateProfile(User $user, ListingOrganization $organization, array $payload, Request $request): ListingOrganization
    {
        $this->listing->assertOrganizationAccess($user, $organization, ['OWNER','ADMIN']);
        if (!in_array($organization->kyb_status, ['DRAFT','INFORMATION_REQUESTED','REVERIFICATION_REQUIRED'], true)) throw new RuntimeException('Organization KYB is not editable in its current state.');
        $before=$organization->toArray(); $organization->fill($payload)->save(); $this->invalidateApprovedKyb($user,$organization,'KYB_PROFILE_CHANGED',$request); $this->audit($user,$organization,'listing.kyb.profile.updated',$before,$organization->fresh()->toArray(),$request); return $organization->fresh();
    }

    public function savePerson(User $user, ListingOrganization $organization, array $payload, Request $request): ListingOrganizationPerson
    {
        $this->listing->assertOrganizationAccess($user,$organization,['OWNER','ADMIN']);
        if (($payload['person_type']??'')==='AUTHORIZED_REPRESENTATIVE' && (int)($payload['linked_user_id']??0)!==(int)$user->id) throw new RuntimeException('The submitting representative must link to the authenticated ExaEarn user.');
        foreach (['sanctions_status','pep_status','adverse_media_status'] as $field) $payload[$field]=config('listing.screening_provider_connected')?($payload[$field]??'NOT_CHECKED'):'NOT_CONNECTED';
        $person=ListingOrganizationPerson::query()->updateOrCreate(['organization_id'=>$organization->id,'person_uuid'=>$payload['person_uuid']??(string)Str::uuid()],array_merge($payload,['organization_id'=>$organization->id]));
        if ($person->person_type==='UBO') { $total=(float)ListingOrganizationPerson::query()->where('organization_id',$organization->id)->where('person_type','UBO')->sum('ownership_percentage'); if ($total>100.0001) { $person->delete(); throw new RuntimeException('Declared beneficial ownership cannot exceed 100%.'); } }
        $this->invalidateApprovedKyb($user,$organization,'KYB_PERSON_CHANGED',$request);
        $this->audit($user,$organization,'listing.kyb.person.saved',null,$person->toArray(),$request); return $person;
    }

    public function acceptDeclarations(User $user, ListingOrganization $organization, Request $request): void
    {
        $this->listing->assertOrganizationAccess($user,$organization,['OWNER','ADMIN']);
        foreach (['INFORMATION_ACCURATE','AUTHORIZED_TO_ACT','MATERIAL_CHANGE_NOTICE','DOCUMENTS_AUTHENTIC'] as $type) ListingOrganizationDeclaration::query()->updateOrCreate(['organization_id'=>$organization->id,'declaration_type'=>$type,'declaration_version'=>config('listing.kyb_declaration_version')],['user_id'=>$user->id,'accepted_at'=>now(),'ip_address_hash'=>hash('sha256',(string)$request->ip()),'user_agent_hash'=>hash('sha256',(string)$request->userAgent())]);
        $this->audit($user,$organization,'listing.kyb.declarations.accepted',null,['version'=>config('listing.kyb_declaration_version')],$request);
    }

    public function storeDocument(User $user, ListingOrganization $organization, array $payload, Request $request): ListingOrganizationDocument
    {
        $this->listing->assertOrganizationAccess($user,$organization,['OWNER','ADMIN','CONTRIBUTOR']); $file=$payload['file']; $version=(int)ListingOrganizationDocument::query()->where('organization_id',$organization->id)->where('document_type',$payload['document_type'])->max('version')+1; $uuid=(string)Str::uuid(); $stored=app(ListingDocumentSecurityService::class)->storeQuarantined($file,'kyb',(string)$organization->organization_uuid,$uuid);
        $document=ListingOrganizationDocument::query()->create(['document_uuid'=>$uuid,'organization_id'=>$organization->id,'uploaded_by'=>$user->id,'document_type'=>$payload['document_type'],'version'=>$version,'original_name'=>$file->getClientOriginalName(),'storage_disk'=>$stored['storage_disk'],'storage_path'=>$stored['storage_path'],'mime_type'=>$stored['mime_type'],'size_bytes'=>$stored['size_bytes'],'checksum_sha256'=>$stored['checksum_sha256'],'scan_status'=>'QUARANTINED','expiry_date'=>$payload['expiry_date']??null]);
        $this->invalidateApprovedKyb($user,$organization,'KYB_DOCUMENT_REPLACED',$request);
        $this->audit($user,$organization,'listing.kyb.document.uploaded',null,$document->toArray(),$request); app(ListingDocumentSecurityService::class)->queue('kyb',$document); return $document;
    }

    public function download(User $user, ListingOrganization $organization, ListingOrganizationDocument $document): string { $this->listing->assertOrganizationAccess($user,$organization); if($document->organization_id!==$organization->id)throw new RuntimeException('Document access denied.'); app(ListingDocumentSecurityService::class)->assertDownloadable($document); return Storage::disk($document->storage_disk)->path($document->storage_path); }

    public function invite(User $user, ListingOrganization $organization, array $payload, Request $request): array
    {
        $this->listing->assertOrganizationAccess($user,$organization,['OWNER','ADMIN']); $token=Str::random(64); $invitation=ListingOrganizationInvitation::query()->create(['invitation_uuid'=>(string)Str::uuid(),'organization_id'=>$organization->id,'invited_by'=>$user->id,'email'=>strtolower($payload['email']),'role'=>$payload['role'],'token_hash'=>hash('sha256',$token),'status'=>'PENDING','expires_at'=>now()->addDays(7)]); Notification::route('mail',$invitation->email)->notify(new ListingOrganizationInvitationNotification($token,$organization->project_name,$invitation->role)); $this->audit($user,$organization,'listing.organization.invitation.created',null,$invitation->toArray(),$request); return ['invitation'=>$invitation];
    }

    public function acceptInvitation(User $user, string $token, Request $request): ListingTeamMember
    {
        return DB::transaction(function()use($user,$token,$request){$invitation=ListingOrganizationInvitation::query()->where('token_hash',hash('sha256',$token))->lockForUpdate()->firstOrFail(); if($invitation->status!=='PENDING'||$invitation->expires_at->isPast()||strtolower($user->email)!==$invitation->email)throw new RuntimeException('Invitation is invalid, expired, or belongs to another user.'); $member=ListingTeamMember::query()->updateOrCreate(['organization_id'=>$invitation->organization_id,'email'=>$invitation->email],['user_id'=>$user->id,'role'=>$invitation->role,'status'=>'ACTIVE']); $invitation->forceFill(['status'=>'ACCEPTED','accepted_at'=>now()])->save(); $this->audit($user,$invitation->organization,'listing.organization.invitation.accepted',null,$member->toArray(),$request); return $member;});
    }

    public function submit(User $user, ListingOrganization $organization, Request $request): ListingOrganization
    {
        $this->listing->assertOrganizationAccess($user,$organization,['OWNER','ADMIN']); $missing=$this->progress($organization)['missing']; if($missing!==[])throw new RuntimeException('KYB is incomplete: '.implode(', ',$missing)); if(!in_array($organization->kyb_status,['DRAFT','INFORMATION_REQUESTED','REVERIFICATION_REQUIRED'],true))throw new RuntimeException('KYB cannot be submitted from its current state.'); $before=$organization->toArray(); $organization->forceFill(['kyb_status'=>'SUBMITTED','kyb_progress'=>100,'kyb_current_stage'=>'INITIAL_REVIEW','kyb_submitted_at'=>now(),'screening_status'=>config('listing.screening_provider_connected')?'PENDING':'NOT_CONNECTED','registry_verification_status'=>config('listing.registry_provider_connected')?'PENDING':'NOT_CONNECTED'])->save(); $this->audit($user,$organization,'listing.kyb.submitted',$before,$organization->fresh()->toArray(),$request); return $organization->fresh();
    }

    public function updateMember(User $user, ListingOrganization $organization, ListingTeamMember $member, array $payload, Request $request): ListingTeamMember
    {
        $this->listing->assertOrganizationAccess($user,$organization,['OWNER','ADMIN']); if($member->organization_id!==$organization->id)throw new RuntimeException('Member access denied.'); if($member->role==='OWNER')throw new RuntimeException('Ownership must be transferred through the protected ownership workflow.'); $before=$member->toArray(); $member->forceFill($payload)->save(); $this->audit($user,$organization,'listing.organization.member.updated',$before,$member->fresh()->toArray(),$request); return $member->fresh();
    }

    public function transferOwnership(User $user, ListingOrganization $organization, ListingTeamMember $target, Request $request): ListingOrganization
    {
        $this->listing->assertOrganizationAccess($user,$organization,['OWNER']); if($target->organization_id!==$organization->id||$target->status!=='ACTIVE'||!$target->user_id)throw new RuntimeException('Ownership target must be an active canonical user member.'); if((int)$target->user_id===(int)$user->id)throw new RuntimeException('The target already owns this organization.'); return DB::transaction(function()use($user,$organization,$target,$request){$before=$organization->toArray(); ListingTeamMember::query()->where('organization_id',$organization->id)->where('user_id',$user->id)->update(['role'=>'ADMIN']); $target->forceFill(['role'=>'OWNER'])->save(); $organization->forceFill(['owner_user_id'=>$target->user_id])->save(); $this->audit($user,$organization,'listing.organization.ownership.transferred',$before,$organization->fresh()->toArray(),$request); return $organization->fresh();});
    }

    public function progress(ListingOrganization $organization): array
    {
        $organization->loadMissing(['people','kybDocuments','declarations']); $sections=['organization'=>(bool)($organization->legal_name&&$organization->project_name&&$organization->organization_type&&$organization->operating_country),'legal_entity'=>(bool)($organization->registration_details&&$organization->registered_country&&$organization->incorporation_date&&$organization->registered_address),'business'=>(bool)($organization->business_description&&$organization->business_information),'directors'=>$organization->people->where('person_type','DIRECTOR')->isNotEmpty(),'ownership'=>$organization->people->where('person_type','UBO')->isNotEmpty(),'representative'=>$organization->people->where('person_type','AUTHORIZED_REPRESENTATIVE')->where('authority_confirmed',true)->isNotEmpty(),'regulatory'=>(bool)$organization->regulatory_information,'documents'=>collect(config('listing.required_kyb_documents'))->diff($organization->kybDocuments->pluck('document_type'))->isEmpty(),'declarations'=>$organization->declarations->where('declaration_version',config('listing.kyb_declaration_version'))->pluck('declaration_type')->unique()->count()===4]; $missing=array_keys(array_filter($sections,fn($complete)=>!$complete)); $percentages=collect($sections)->map(fn($complete)=>$complete?100:0)->all(); return ['sections'=>$percentages,'overall'=>(int)round(array_sum($percentages)/count($percentages)),'missing'=>$missing,'current_stage'=>strtoupper($missing[0]??'READY_FOR_SUBMISSION')];
    }

    private function invalidateApprovedKyb(User $user, ListingOrganization $organization, string $reason, Request $request): void
    {
        if ($organization->getOriginal('kyb_status') !== 'APPROVED' && $organization->kyb_status !== 'APPROVED') {
            return;
        }

        $before = $organization->fresh()->toArray();
        $organization->forceFill(['kyb_status' => 'REVERIFICATION_REQUIRED', 'kyb_current_stage' => 'REVERIFICATION_REQUIRED'])->save();
        $this->audit($user, $organization, 'listing.kyb.approval.invalidated', $before, $organization->fresh()->toArray(), $request);
    }

    private function audit(User $user, ListingOrganization $organization, string $action, ?array $before, ?array $after, Request $request): void { ListingAuditLog::query()->create(['audit_uuid'=>(string)Str::uuid(),'actor_type'=>'user','actor_id'=>$user->id,'action'=>$action,'resource_type'=>'listing_organization','resource_id'=>$organization->id,'before'=>$before,'after'=>$after,'reason'=>$action==='listing.kyb.approval.invalidated'?'MATERIAL_KYB_CHANGE':null,'ip_address'=>$request->ip(),'user_agent'=>$request->userAgent()]); }
}
