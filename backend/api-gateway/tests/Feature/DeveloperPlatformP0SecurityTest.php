<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ComplianceJurisdiction;
use App\Models\ComplianceProduct;
use App\Models\DeveloperOrganization;
use App\Models\InstitutionalAccount;
use App\Models\User;
use App\Services\DeveloperProductionAccessService;
use App\Services\DeveloperWorkspaceService;
use App\Services\Security\CanonicalClientIp;
use App\Services\Security\DnsResolver;
use App\Services\Security\WebhookDestinationValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use RuntimeException;
use Tests\TestCase;

class DeveloperPlatformP0SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_ssrf_validator_rejects_non_public_destinations_and_authority_tricks(): void
    {
        $cases = [
            ['http://example.com/hook',['93.184.216.34']],
            ['https://user:pass@example.com/hook',['93.184.216.34']],
            ['https://localhost/hook',['127.0.0.1']],
            ['https://localhost./hook',['127.0.0.1']],
            ['https://127.0.0.1/hook',['127.0.0.1']],
            ['https://127.1/hook',['127.0.0.1']],
            ['https://2130706433/hook',['127.0.0.1']],
            ['https://example.com/hook',['0.0.0.0']],
            ['https://example.com/hook',['10.1.2.3']],
            ['https://example.com/hook',['172.16.1.2']],
            ['https://example.com/hook',['192.168.1.2']],
            ['https://example.com/hook',['169.254.169.254']],
            ['https://example.com/hook',['::1']],
            ['https://example.com/hook',['fc00::1']],
            ['https://example.com/hook',['fe80::1']],
            ['https://example.com/hook',['::ffff:127.0.0.1']],
            ['https://example.com:8443/hook',['93.184.216.34']],
            ['file:///etc/passwd',[]],
        ];
        foreach ($cases as [$url,$answers]) {
            try {
                $this->validator($answers)->validate($url);
                $this->fail("Unsafe destination accepted: {$url}");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_webhook_validator_accepts_public_https_and_rejects_mixed_dns_answers(): void
    {
        $safe=$this->validator(['93.184.216.34','2606:2800:220:1:248:1893:25c8:1946'])->validate('https://hooks.example.com/events?source=exa');
        $this->assertSame(443,$safe['port']);
        $this->assertSame('https://hooks.example.com/events?source=exa',$safe['url']);
        $this->expectException(RuntimeException::class);
        $this->validator(['93.184.216.34','10.0.0.5'])->validate('https://rebind.example.com/hook');
    }

    public function test_untrusted_forwarding_headers_do_not_change_canonical_client_ip(): void
    {
        $request=Request::create('/api/developer/v1/account','GET',[],[],[],[
            'REMOTE_ADDR'=>'203.0.113.20',
            'HTTP_X_FORWARDED_FOR'=>'8.8.8.8',
            'HTTP_X_REAL_IP'=>'1.1.1.1',
            'HTTP_FORWARDED'=>'for=9.9.9.9',
        ]);
        $this->assertSame('203.0.113.20',app(CanonicalClientIp::class)->for($request));
    }

    public function test_configured_trusted_proxy_supplies_the_canonical_client_ip(): void
    {
        $request=Request::create('/api/developer/v1/account','GET',[],[],[],[
            'REMOTE_ADDR'=>'10.10.0.5','HTTP_X_FORWARDED_FOR'=>'198.51.100.8, 10.10.0.4',
        ]);
        $request->setTrustedProxies(['10.10.0.4','10.10.0.5'],SymfonyRequest::HEADER_X_FORWARDED_FOR);
        $this->assertSame('198.51.100.8',app(CanonicalClientIp::class)->for($request));
    }

    public function test_organization_runtime_uses_institution_and_fails_closed_after_kyb_suspension(): void
    {
        config(['developer_api.production_access.organization_enabled'=>true]);
        $owner=User::factory()->create(['verified_country'=>'US','account_status'=>'FULLY_ACTIVE']);
        $institution=InstitutionalAccount::query()->create([
            'institution_uuid'=>'inst-p0','master_user_id'=>$owner->id,'legal_name'=>'P0 Institution','country_of_incorporation'=>'NG','business_type'=>'LIMITED_COMPANY',
            'status'=>'ACTIVE','kyb_status'=>'APPROVED','compliance_status'=>'CLEARED','risk_rating'=>'LOW',
        ]);
        ComplianceJurisdiction::query()->create(['country_code'=>'NG','country_name'=>'Nigeria','status'=>'ALLOWED','risk_level'=>'LOW','policy_version'=>'p0-test']);
        ComplianceProduct::query()->create(['product_code'=>'SPOT','risk_category'=>'HIGH','default_policy'=>'ALLOW']);
        $workspaces=app(DeveloperWorkspaceService::class);
        $organization=$workspaces->createOrganization($owner,'P0 Org');
        $organization->update(['institution_id'=>$institution->id,'verification_status'=>'verified','authorized_representative_status'=>'verified']);
        $project=$workspaces->provisionProject($owner,$organization->workspace,['name'=>'Institution Runtime']);
        $project->environments()->where('type','production')->update(['status'=>'active']);
        $access=\App\Models\DeveloperProductionAccessRequest::query()->create([
            'request_uuid'=>'req-p0','project_id'=>$project->id,'environment_id'=>$project->environments()->where('type','production')->value('id'),
            'submitted_by'=>$owner->id,'applicant_type'=>'organization','use_case'=>'trading_application','status'=>'approved','jurisdiction'=>'NG',
            'request_context'=>[],'idempotency_key'=>'p0-runtime','submitted_at'=>now(),'decided_at'=>now(),
        ]);
        \App\Models\DeveloperProductionCapability::query()->create(['request_id'=>$access->id,'project_id'=>$project->id,'capability'=>'spot.read','status'=>'approved']);
        app(DeveloperProductionAccessService::class)->assertCapabilities($project->fresh()->load(['environments','organization.owner','user']),['spot.read']);
        $this->assertDatabaseHas('compliance_decision_logs',['institution_id'=>$institution->id,'account_type'=>'INSTITUTIONAL','jurisdiction'=>'NG']);

        $institution->update(['kyb_status'=>'SUSPENDED']);
        $this->expectException(RuntimeException::class);
        app(DeveloperProductionAccessService::class)->assertCapabilities($project->fresh()->load(['environments','organization.owner','user']),['spot.read']);
    }

    private function validator(array $answers): WebhookDestinationValidator
    {
        return new WebhookDestinationValidator(new class($answers) extends DnsResolver {
            public function __construct(private readonly array $answers) {}
            public function resolve(string $host): array { return $this->answers; }
        });
    }
}
