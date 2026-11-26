<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ComplianceJurisdiction;
use App\Models\ListingOrganization;
use App\Models\ListingOrganizationInvitation;
use App\Models\ListingTeamMember;
use App\Models\User;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use App\Contracts\MalwareScannerInterface;
use App\Models\ListingOrganizationDocument;
use App\Services\ListingDocumentSecurityService;
use Illuminate\Support\Str;
use App\Http\Middleware\RateLimitMiddleware;
use Tests\TestCase;

class ListingKybOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(RateLimitMiddleware::class);
        Storage::fake('local');
        Storage::fake('listing_quarantine');
        Queue::fake();
        Notification::fake();
        ComplianceJurisdiction::query()->create(['country_code'=>'NG','country_name'=>'Nigeria','status'=>'ALLOWED','risk_level'=>'STANDARD','policy_version'=>'kyb-test']);
    }

    public function test_kyb_is_organization_scoped_structured_and_fails_closed_until_complete(): void
    {
        $owner=User::factory()->create(); $outsider=User::factory()->create();
        $organization=$this->actingAs($owner)->postJson('/api/listing/organizations',['legal_name'=>'Kyb Labs Ltd','project_name'=>'Kyb Labs','jurisdiction'=>'NG'])->assertCreated()->json('data');
        $this->actingAs($outsider)->getJson("/api/listing/organizations/{$organization['id']}/kyb")->assertNotFound();
        $this->actingAs($owner)->postJson("/api/listing/organizations/{$organization['id']}/kyb/submit")->assertUnprocessable()->assertJsonFragment(['message'=>'KYB is incomplete: organization, legal_entity, business, directors, ownership, representative, regulatory, documents, declarations']);

        $this->actingAs($owner)->patchJson("/api/listing/organizations/{$organization['id']}/kyb",[
            'legal_name'=>'Kyb Labs Ltd','project_name'=>'Kyb Labs','organization_type'=>'PRIVATE_COMPANY','operating_country'=>'NG','registered_country'=>'NG','jurisdiction'=>'NG','incorporation_date'=>'2020-01-01','registered_address'=>['line1'=>'1 Secure Road'],'registration_details'=>['registration_number'=>'RC-123'],'business_description'=>'Blockchain infrastructure services.','business_information'=>['products_services'=>'Infrastructure'],'regulatory_information'=>['license_status'=>'NOT_REQUIRED_DECLARED'],
        ])->assertOk();
        $this->person($owner,$organization['id'],'DIRECTOR',['full_legal_name'=>'Ada Director','role_title'=>'Director'])->assertCreated()->assertJsonPath('data.sanctions_status','NOT_CONNECTED');
        $this->person($owner,$organization['id'],'UBO',['full_legal_name'=>'Ada Owner','ownership_percentage'=>'60','ownership_type'=>'DIRECT_OWNERSHIP'])->assertCreated();
        $this->person($owner,$organization['id'],'UBO',['full_legal_name'=>'Overflow Owner','ownership_percentage'=>'50','ownership_type'=>'DIRECT_OWNERSHIP'])->assertUnprocessable();
        $this->person($owner,$organization['id'],'AUTHORIZED_REPRESENTATIVE',['linked_user_id'=>$owner->id,'full_legal_name'=>$owner->name,'email'=>$owner->email,'authority_basis'=>'Director resolution','authority_confirmed'=>true])->assertCreated();

        foreach (['INCORPORATION_CERTIFICATE','SHAREHOLDER_REGISTER','REGISTERED_ADDRESS_PROOF','AUTHORIZATION_LETTER'] as $type) {
            $this->actingAs($owner)->post("/api/listing/organizations/{$organization['id']}/kyb/documents",['document_type'=>$type,'file'=>UploadedFile::fake()->create(strtolower($type).'.pdf',100,'application/pdf')])->assertCreated()->assertJsonPath('data.scan_status','QUARANTINED')->assertJsonMissingPath('data.storage_path');
        }
        $this->actingAs($owner)->postJson("/api/listing/organizations/{$organization['id']}/kyb/declarations",['accepted'=>true])->assertOk();
        $this->actingAs($owner)->postJson("/api/listing/organizations/{$organization['id']}/kyb/submit")->assertAccepted()->assertJsonPath('data.kyb_status','SUBMITTED')->assertJsonPath('data.screening_status','NOT_CONNECTED');
        $admin=$this->admin();
        $this->actingAs($admin)->postJson("/api/admin/v1/listing-center/kyb-organizations/{$organization['id']}/decision",['status'=>'APPROVED'])->assertUnprocessable()->assertJsonFragment(['message'=>'KYB approval blocked: current INCORPORATION_CERTIFICATE document is not malware-cleared.']);
        $this->actingAs($admin)->postJson("/api/admin/v1/listing-center/kyb-organizations/{$organization['id']}/decision",['status'=>'APPROVED','public_message'=>'Organization verification completed.','manual_verification_evidence'=>['registry_extract'=>'reviewed','screening_workpapers'=>'reviewed']])->assertUnprocessable()->assertJsonFragment(['message'=>'KYB approval blocked: current INCORPORATION_CERTIFICATE document is not malware-cleared.']);

        $this->app->instance(MalwareScannerInterface::class, new class implements MalwareScannerInterface {
            public function providerName(): string { return 'safe-test-scanner'; }
            public function health(): array { return ['status'=>'CONNECTED']; }
            public function supports(string $mimeType): bool { return true; }
            public function scan(string $absolutePath,string $checksumSha256,string $mimeType): array { return ['result'=>'CLEAN','scan_reference'=>'test-'.$checksumSha256,'engine_version'=>'safe-fixture-1']; }
        });
        foreach(ListingOrganizationDocument::query()->where('organization_id',$organization['id'])->get() as $document) app(ListingDocumentSecurityService::class)->scan('kyb',$document->id,$document->checksum_sha256);
        $firstClean=ListingOrganizationDocument::query()->where('organization_id',$organization['id'])->firstOrFail(); $attemptsBefore=\Illuminate\Support\Facades\DB::table('listing_document_scan_attempts')->count(); app(ListingDocumentSecurityService::class)->scan('kyb',$firstClean->id,$firstClean->checksum_sha256); $this->assertSame($attemptsBefore,\Illuminate\Support\Facades\DB::table('listing_document_scan_attempts')->count());

        $replacement=$this->actingAs($owner)->post("/api/listing/organizations/{$organization['id']}/kyb/documents",['document_type'=>'INCORPORATION_CERTIFICATE','file'=>UploadedFile::fake()->create('replacement.pdf',100,'application/pdf')])->assertCreated()->assertJsonPath('data.version',2)->assertJsonPath('data.scan_status','QUARANTINED')->json('data');
        $this->actingAs($admin)->postJson("/api/admin/v1/listing-center/kyb-organizations/{$organization['id']}/decision",['status'=>'APPROVED','manual_verification_evidence'=>['registry_extract'=>'reviewed','screening_workpapers'=>'reviewed']])->assertUnprocessable();
        $replacementModel=ListingOrganizationDocument::query()->where('document_uuid',$replacement['document_uuid'])->firstOrFail(); try{app(ListingDocumentSecurityService::class)->scan('kyb',$replacementModel->id,str_repeat('0',64));$this->fail('Checksum mismatch must fail closed.');}catch(\RuntimeException $exception){$this->assertSame('Document checksum changed before scanning.',$exception->getMessage());} app(ListingDocumentSecurityService::class)->scan('kyb',$replacementModel->id,$replacementModel->checksum_sha256);
        $this->actingAs($admin)->postJson("/api/admin/v1/listing-center/kyb-organizations/{$organization['id']}/decision",['status'=>'APPROVED','public_message'=>'Organization verification completed.','manual_verification_evidence'=>['registry_extract'=>'reviewed','screening_workpapers'=>'reviewed']])->assertOk()->assertJsonPath('data.kyb_status','APPROVED');
        $this->assertDatabaseHas('listing_organization_people',['organization_id'=>$organization['id'],'person_type'=>'UBO','full_legal_name'=>'Ada Owner']);
        $this->assertDatabaseMissing('listing_organization_people',['full_legal_name'=>'Overflow Owner']);
    }

    public function test_invitation_is_recipient_bound_and_ownership_requires_protected_transfer(): void
    {
        $owner=User::factory()->create(); $invitee=User::factory()->create(['email'=>'invitee@example.com']); $other=User::factory()->create();
        $organization=$this->actingAs($owner)->postJson('/api/listing/organizations',['legal_name'=>'Invite Ltd','project_name'=>'Invite','jurisdiction'=>'NG'])->assertCreated()->json('data');
        $response=$this->actingAs($owner)->postJson("/api/listing/organizations/{$organization['id']}/invitations",['email'=>$invitee->email,'role'=>'ADMIN'])->assertCreated()->assertJsonMissingPath('data.token');
        $this->assertSame('PENDING',$response->json('data.invitation.status'));

        $token=Str::random(64); ListingOrganizationInvitation::query()->create(['invitation_uuid'=>(string)Str::uuid(),'organization_id'=>$organization['id'],'invited_by'=>$owner->id,'email'=>$invitee->email,'role'=>'ADMIN','token_hash'=>hash('sha256',$token),'expires_at'=>now()->addDay()]);
        $this->actingAs($other)->postJson('/api/listing/invitations/accept',['token'=>$token])->assertUnprocessable();
        $member=$this->actingAs($invitee)->postJson('/api/listing/invitations/accept',['token'=>$token])->assertOk()->json('data');
        $this->actingAs($owner)->patchJson("/api/listing/organizations/{$organization['id']}/members/".ListingTeamMember::query()->where('organization_id',$organization['id'])->where('role','OWNER')->value('id'),['status'=>'REVOKED'])->assertUnprocessable();

        $this->actingAs($owner)->withSession(['auth_recent_at'=>time()])->postJson("/api/listing/organizations/{$organization['id']}/ownership-transfer",['member_id'=>$member['id'],'confirmed'=>true])->assertOk()->assertJsonPath('data.owner_user_id',$invitee->id);
        $this->assertDatabaseHas('listing_team_members',['id'=>$member['id'],'role'=>'OWNER']);
    }

    public function test_listing_submission_requires_approved_organization_kyb(): void
    {
        config(['listing.require_approved_kyb_for_listing_submission'=>true]); $owner=User::factory()->create();
        $organization=$this->actingAs($owner)->postJson('/api/listing/organizations',['legal_name'=>'Gate Ltd','project_name'=>'Gate','jurisdiction'=>'NG'])->assertCreated()->json('data');
        $application=$this->actingAs($owner)->postJson("/api/listing/organizations/{$organization['id']}/applications",['idempotency_key'=>'kyb-gate','application_type'=>'NEW_TOKEN_LISTING','project_information'=>['name'=>'Gate'],'asset_information'=>['name'=>'Gate','symbol'=>'GATE'],'blockchain_information'=>['network'=>'ETHEREUM'],'tokenomics'=>['total_supply'=>'1'],'technology'=>['repository'=>'https://example.com'],'security'=>['audit_reports'=>['report']],'legal_compliance'=>['legal_opinion'=>'declared'],'market_community'=>['website'=>'https://example.com'],'liquidity'=>['market_maker'=>'declared'],'listing_request'=>['requested_pairs'=>['GATE/USDT']]])->assertCreated()->json('data');
        $this->actingAs($owner)->postJson("/api/listing/applications/{$application['reference']}/submit",['authorized_declaration'=>true])->assertUnprocessable()->assertJsonFragment(['message'=>'Organization KYB approval is required before a listing application can be submitted.']);
    }

    private function person(User $user,int $organizationId,string $type,array $payload)
    {
        return $this->actingAs($user)->postJson("/api/listing/organizations/{$organizationId}/kyb/people",array_merge(['person_type'=>$type],$payload));
    }

    private function admin(): Admin
    {
        $role=Role::query()->firstOrCreate(['name'=>'super_admin']);
        return Admin::query()->create(['name'=>'KYB Reviewer','email'=>'kyb-reviewer@example.com','password'=>Hash::make('password'),'role_id'=>$role->id,'status'=>'active','two_factor_enabled'=>true]);
    }
}
