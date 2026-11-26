<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ListingOrganization;
use App\Models\ListingOrganizationDocument;
use App\Models\ListingTeamMember;
use App\Services\ListingKybService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListingKybController extends Controller
{
    public function __construct(private readonly ListingKybService $kyb) {}

    public function dashboard(Request $request, int $organizationId): JsonResponse { try{return response()->json(['data'=>$this->kyb->dashboard($request->user(),$this->organization($organizationId))]);}catch(RuntimeException){return response()->json(['message'=>'Organization not found.'],404);} }

    public function update(Request $request, int $organizationId): JsonResponse
    {
        $payload=$request->validate([
            'project_name'=>['sometimes','required','string','max:140'],'legal_name'=>['sometimes','required','string','max:180'],'trading_name'=>['nullable','string','max:180'],'organization_type'=>['sometimes','required','in:PRIVATE_COMPANY,PUBLIC_COMPANY,FOUNDATION,DAO,NON_PROFIT,PARTNERSHIP,SOLE_PROPRIETORSHIP,OTHER'],'website'=>['nullable','url','max:255'],'business_email'=>['nullable','email','max:255'],'operating_country'=>['sometimes','required','string','size:2'],'registered_country'=>['sometimes','required','string','size:2'],'jurisdiction'=>['sometimes','required','string','size:2'],'project_category'=>['nullable','string','max:80'],'incorporation_date'=>['nullable','date','before_or_equal:today'],'registered_address'=>['nullable','array'],'registration_details'=>['nullable','array'],'business_description'=>['nullable','string','max:5000'],'business_information'=>['nullable','array'],'regulatory_information'=>['nullable','array'],'ownership_structure'=>['nullable','array'],
        ]);
        try{return response()->json(['data'=>$this->kyb->updateProfile($request->user(),$this->organization($organizationId),$payload,$request)]);}catch(RuntimeException $e){return response()->json(['message'=>$e->getMessage()],422);}
    }

    public function person(Request $request, int $organizationId): JsonResponse
    {
        $payload=$request->validate(['person_uuid'=>['nullable','uuid'],'person_type'=>['required','in:DIRECTOR,UBO,AUTHORIZED_REPRESENTATIVE'],'linked_user_id'=>['nullable','integer','exists:users,id'],'full_legal_name'=>['required','string','max:180'],'role_title'=>['nullable','string','max:120'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:40'],'nationality'=>['nullable','string','size:2'],'residence_country'=>['nullable','string','size:2'],'date_of_birth'=>['nullable','date','before:today'],'ownership_percentage'=>['nullable','numeric','min:0','max:100'],'control_percentage'=>['nullable','numeric','min:0','max:100'],'ownership_type'=>['nullable','in:DIRECT_OWNERSHIP,INDIRECT_OWNERSHIP,CORPORATE_SHAREHOLDER,TRUST_FOUNDATION,OTHER_CONTROL'],'relationship'=>['nullable','string','max:120'],'professional_profile'=>['nullable','string','max:3000'],'authority_basis'=>['nullable','string','max:180'],'authority_confirmed'=>['nullable','boolean']]);
        try{return response()->json(['data'=>$this->kyb->savePerson($request->user(),$this->organization($organizationId),$payload,$request)],201);}catch(RuntimeException $e){return response()->json(['message'=>$e->getMessage()],422);}
    }

    public function declarations(Request $request, int $organizationId): JsonResponse { $request->validate(['accepted'=>['required','accepted']]); $this->kyb->acceptDeclarations($request->user(),$this->organization($organizationId),$request); return response()->json(['status'=>'accepted']); }

    public function upload(Request $request, int $organizationId): JsonResponse
    {
        $payload=$request->validate(['document_type'=>['required','in:INCORPORATION_CERTIFICATE,BUSINESS_REGISTRATION,ARTICLES_CONSTITUTION,SHAREHOLDER_REGISTER,DIRECTOR_REGISTER,REGISTERED_ADDRESS_PROOF,OWNERSHIP_STRUCTURE,AUTHORIZATION_LETTER,REGULATORY_LICENSE,TAX_DOCUMENT,OTHER'],'file'=>['required','file','max:'.config('listing.documents.max_size_kb'),'mimes:pdf,png,jpg,jpeg'],'expiry_date'=>['nullable','date','after:today']]);
        try{return response()->json(['data'=>$this->kyb->storeDocument($request->user(),$this->organization($organizationId),$payload,$request)],201);}catch(RuntimeException $e){return response()->json(['message'=>$e->getMessage()],422);}
    }

    public function download(Request $request, int $organizationId, string $documentUuid): BinaryFileResponse|JsonResponse { $organization=$this->organization($organizationId); $document=ListingOrganizationDocument::query()->where('organization_id',$organizationId)->where('document_uuid',$documentUuid)->firstOrFail(); try{return response()->download($this->kyb->download($request->user(),$organization,$document),basename(str_replace(["\r","\n"],'',$document->original_name)),['Content-Type'=>'application/octet-stream']);}catch(RuntimeException $e){return response()->json(['message'=>$e->getMessage()],422);} }

    public function invite(Request $request, int $organizationId): JsonResponse { $payload=$request->validate(['email'=>['required','email','max:255'],'role'=>['required','in:ADMIN,CONTRIBUTOR,VIEWER']]); try{return response()->json(['data'=>$this->kyb->invite($request->user(),$this->organization($organizationId),$payload,$request)],201);}catch(RuntimeException $e){return response()->json(['message'=>$e->getMessage()],422);} }
    public function acceptInvitation(Request $request): JsonResponse { $payload=$request->validate(['token'=>['required','string','size:64']]); try{return response()->json(['data'=>$this->kyb->acceptInvitation($request->user(),$payload['token'],$request)]);}catch(RuntimeException $e){return response()->json(['message'=>$e->getMessage()],422);} }
    public function submit(Request $request, int $organizationId): JsonResponse { try{return response()->json(['data'=>$this->kyb->submit($request->user(),$this->organization($organizationId),$request)],202);}catch(RuntimeException $e){return response()->json(['message'=>$e->getMessage()],422);} }
    public function updateMember(Request $request, int $organizationId, int $memberId): JsonResponse { $payload=$request->validate(['role'=>['sometimes','in:ADMIN,CONTRIBUTOR,VIEWER'],'status'=>['sometimes','in:ACTIVE,REVOKED']]); try{return response()->json(['data'=>$this->kyb->updateMember($request->user(),$this->organization($organizationId),ListingTeamMember::query()->findOrFail($memberId),$payload,$request)]);}catch(RuntimeException $e){return response()->json(['message'=>$e->getMessage()],422);} }
    public function transferOwnership(Request $request, int $organizationId): JsonResponse { $payload=$request->validate(['member_id'=>['required','integer','exists:listing_team_members,id'],'confirmed'=>['required','accepted']]); try{return response()->json(['data'=>$this->kyb->transferOwnership($request->user(),$this->organization($organizationId),ListingTeamMember::query()->findOrFail($payload['member_id']),$request)]);}catch(RuntimeException $e){return response()->json(['message'=>$e->getMessage()],422);} }
    private function organization(int $id): ListingOrganization { return ListingOrganization::query()->findOrFail($id); }
}
