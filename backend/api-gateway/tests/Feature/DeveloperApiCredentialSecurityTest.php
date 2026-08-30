<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DeveloperApiKey;
use App\Models\DeveloperAuditLog;
use App\Models\DeveloperOrganizationMembership;
use App\Models\User;
use App\Services\DeveloperApiKeyService;
use App\Services\DeveloperApiSignatureService;
use App\Services\DeveloperRealtimeService;
use App\Services\DeveloperWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DeveloperApiCredentialSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_secret_is_returned_once_and_never_serialized_or_audited(): void
    {
        [$user,$project]=$this->personalProject();
        $created=app(DeveloperApiKeyService::class)->createKey($user->id,$project,['name'=>'Read only','environment'=>'sandbox','permissions'=>['market.read']]);
        $this->assertStringStartsWith('exa_test_',$created['api_key']);
        $this->assertStringStartsWith('exa_sec_',$created['api_secret']);
        $serialized=json_encode($created['key'],JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('encrypted_secret',$serialized);
        $this->assertStringNotContainsString($created['api_secret'],$serialized);
        $this->assertStringNotContainsString($created['api_secret'],DeveloperAuditLog::query()->get()->toJson());
        $this->actingAs($user)->getJson("/api/developer/projects/{$project->id}/keys")->assertOk()->assertJsonMissing(['api_secret'=>$created['api_secret']]);
    }

    public function test_scope_registry_ip_validation_and_production_gate_fail_closed(): void
    {
        [$user,$project]=$this->personalProject();$service=app(DeveloperApiKeyService::class);
        foreach([
            ['permissions'=>['unknown.scope'],'ip_whitelist'=>[]],
            ['permissions'=>['wallet.withdraw'],'ip_whitelist'=>[]],
            ['permissions'=>['market.read'],'ip_whitelist'=>['999.1.1.1']],
            ['permissions'=>['market.read'],'ip_whitelist'=>['2001:db8::/999']],
            ['environment'=>'production','permissions'=>['market.read'],'ip_whitelist'=>[]],
        ] as $case){
            try{$service->createKey($user->id,$project,['name'=>'Invalid','environment'=>$case['environment']??'sandbox']+$case);$this->fail('Unsafe credential policy was accepted.');}catch(RuntimeException){$this->addToAssertionCount(1);}
        }
    }

    public function test_shared_signing_fixture_matches_gateway_contract(): void
    {
        $fixture=json_decode(file_get_contents(base_path('../../contracts/developer-signing-v1.json')),true,512,JSON_THROW_ON_ERROR);
        $signatures=app(DeveloperApiSignatureService::class);
        $canonical=$signatures->canonical($fixture['method'],$fixture['path'],$fixture['query_input'],$fixture['timestamp'],$fixture['nonce'],$fixture['body']);
        $this->assertSame($fixture['canonical'],$canonical);
        $this->assertSame($fixture['signature'],$signatures->sign($fixture['api_secret'],$fixture['method'],$fixture['path'],$fixture['query_input'],$fixture['timestamp'],$fixture['nonce'],$fixture['body']));
    }

    public function test_valid_ipv4_and_ipv6_rules_are_stored_normalized_and_enforced(): void
    {
        [$user,$project]=$this->personalProject();
        $created=app(DeveloperApiKeyService::class)->createKey($user->id,$project,['name'=>'IP key','permissions'=>['account.read'],'ip_whitelist'=>['127.0.0.1/32','2001:db8::/48']]);
        $this->assertSame(['127.0.0.1/32','2001:db8::/48'],$created['key']->ipWhitelists->pluck('cidr')->all());
        $headers=$this->signed($created,'GET','/api/developer/v1/wallet/balances');
        $this->withHeaders($headers)->getJson('/api/developer/v1/wallet/balances')->assertOk()->assertHeader('X-RateLimit-Limit')->assertHeader('X-RateLimit-Remaining');
        $this->withServerVariables(['REMOTE_ADDR'=>'198.51.100.8'])->withHeaders($this->signed($created,'GET','/api/developer/v1/wallet/balances','blocked-ip'))->getJson('/api/developer/v1/wallet/balances')->assertForbidden()->assertJsonPath('error.code','IP_NOT_ALLOWED');
    }

    public function test_disable_enable_rotate_and_revoke_take_effect_immediately(): void
    {
        [$user,$project]=$this->personalProject();$service=app(DeveloperApiKeyService::class);
        $created=$service->createKey($user->id,$project,['name'=>'Lifecycle','permissions'=>['account.read']]);$key=$created['key'];
        $service->setStatus($user->id,$key,'disabled');
        $this->withHeaders($this->signed($created,'GET','/api/developer/v1/wallet/balances','disabled'))->getJson('/api/developer/v1/wallet/balances')->assertUnauthorized();
        $service->setStatus($user->id,$key->fresh(),'active');
        $rotated=$service->rotateSecret($user->id,$key->fresh());
        $this->withHeaders($this->signed($created,'GET','/api/developer/v1/wallet/balances','old-secret'))->getJson('/api/developer/v1/wallet/balances')->assertUnauthorized()->assertJsonPath('error.code','INVALID_SIGNATURE');
        $new=['api_key'=>$created['api_key'],'api_secret'=>$rotated['api_secret']];
        $this->withHeaders($this->signed($new,'GET','/api/developer/v1/wallet/balances','new-secret'))->getJson('/api/developer/v1/wallet/balances')->assertOk();
        $service->revoke($user->id,$key->fresh());
        $this->withHeaders($this->signed($new,'GET','/api/developer/v1/wallet/balances','revoked'))->getJson('/api/developer/v1/wallet/balances')->assertUnauthorized();
        $this->expectException(RuntimeException::class);$service->setStatus($user->id,$key->fresh(),'active');
    }

    public function test_disable_and_revoke_invalidate_private_realtime_sessions(): void
    {
        [$user,$project]=$this->personalProject();$keys=app(DeveloperApiKeyService::class);$realtime=app(DeveloperRealtimeService::class);$created=$keys->createKey($user->id,$project,['name'=>'Realtime','permissions'=>['account.read']]);
        $session=$realtime->createSession($project,$created['key'],['account.balance']);$this->assertNotNull($realtime->validSession($session['session_id']));
        $keys->setStatus($user->id,$created['key'],'disabled');$this->assertNull($realtime->validSession($session['session_id']));
        $keys->setStatus($user->id,$created['key']->fresh(),'active');$replacement=$realtime->createSession($project,$created['key']->fresh(),['account.balance']);
        $keys->revoke($user->id,$created['key']->fresh());$this->assertNull($realtime->validSession($replacement['session_id']));
    }

    public function test_archived_project_suspended_organization_and_environment_mismatch_deny_authentication(): void
    {
        [$user,$project]=$this->personalProject();$service=app(DeveloperApiKeyService::class);$created=$service->createKey($user->id,$project,['name'=>'Parent','permissions'=>['account.read']]);
        $project->update(['status'=>'archived']);
        $this->withHeaders($this->signed($created,'GET','/api/developer/v1/wallet/balances','archived'))->getJson('/api/developer/v1/wallet/balances')->assertForbidden()->assertJsonPath('error.code','PROJECT_INACTIVE');

        [$owner,$organizationProject,$organization]=$this->organizationProject();$orgKey=$service->createKey($owner->id,$organizationProject,['name'=>'Org','permissions'=>['account.read']]);
        $organization->update(['status'=>'suspended']);
        $this->withHeaders($this->signed($orgKey,'GET','/api/developer/v1/wallet/balances','suspended'))->getJson('/api/developer/v1/wallet/balances')->assertForbidden();

        $organization->update(['status'=>'active']);$orgKey['key']->update(['environment'=>'production']);$organizationProject->environments()->where('type','production')->update(['status'=>'active']);
        $this->withHeaders($this->signed($orgKey,'GET','/api/developer/v1/wallet/balances','wrong-env'))->getJson('/api/developer/v1/wallet/balances')->assertForbidden()->assertJsonPath('error.code','ENVIRONMENT_MISMATCH');
    }

    public function test_viewer_cannot_create_or_revoke_organization_credentials(): void
    {
        [$owner,$project,$organization]=$this->organizationProject();$viewer=User::factory()->create();DeveloperOrganizationMembership::query()->create(['organization_id'=>$organization->id,'user_id'=>$viewer->id,'role'=>'viewer','status'=>'active','joined_at'=>now()]);
        $created=app(DeveloperApiKeyService::class)->createKey($owner->id,$project,['name'=>'Owner key','permissions'=>['market.read']]);
        $this->actingAs($viewer)->withSession(['auth_recent_at'=>time()])->postJson("/api/developer/projects/{$project->id}/keys",['name'=>'Viewer key','permissions'=>['market.read']])->assertForbidden();
        $this->actingAs($viewer)->withSession(['auth_recent_at'=>time()])->postJson("/api/developer/credentials/{$created['key']->key_uuid}/revoke")->assertForbidden();
    }

    public function test_scope_and_ip_policy_changes_are_atomic_and_recent_auth_protected(): void
    {
        [$user,$project]=$this->personalProject();$created=app(DeveloperApiKeyService::class)->createKey($user->id,$project,['name'=>'Policy','permissions'=>['market.read']]);$url="/api/developer/credentials/{$created['key']->key_uuid}/policy";
        $this->actingAs($user)->putJson($url,['permissions'=>['account.read'],'ip_whitelist'=>['127.0.0.1']])->assertStatus(428);
        $this->actingAs($user)->withSession(['auth_recent_at'=>time()])->putJson($url,['permissions'=>['account.read'],'ip_whitelist'=>['127.0.0.1']])->assertOk();
        $key=$created['key']->fresh(['permissions','ipWhitelists']);$this->assertSame(['account.read'],$key->permissions->pluck('permission')->all());$this->assertSame(['127.0.0.1'],$key->ipWhitelists->pluck('cidr')->all());
    }

    public function test_flat_compatibility_scope_list_cannot_drift_from_canonical_registry(): void
    {
        $flat=(array)config('developer_api.permissions');$registry=array_keys((array)config('developer_api.scope_registry'));sort($flat);sort($registry);$this->assertSame($flat,$registry);
    }

    private function personalProject(): array
    {
        $user=User::factory()->create(['account_status'=>'FULLY_ACTIVE']);$workspaces=app(DeveloperWorkspaceService::class);$workspace=$workspaces->ensurePersonalWorkspace($user);return[$user,$workspaces->provisionProject($user,$workspace,['name'=>'Credential test'])];
    }

    private function organizationProject(): array
    {
        $owner=User::factory()->create(['account_status'=>'FULLY_ACTIVE']);$workspaces=app(DeveloperWorkspaceService::class);$organization=$workspaces->createOrganization($owner,'Credential Org');$project=$workspaces->provisionProject($owner,$organization->workspace,['name'=>'Organization API']);return[$owner,$project,$organization];
    }

    private function signed(array $credentials,string $method,string $path,string $nonce='nonce',string $query='',string $body='[]'): array
    {
        $timestamp=(string)time();$signature=app(DeveloperApiSignatureService::class)->sign($credentials['api_secret'],$method,$path,$query,$timestamp,$nonce,$body);return['EXA-API-KEY'=>$credentials['api_key'],'EXA-API-TIMESTAMP'=>$timestamp,'EXA-API-NONCE'=>$nonce,'EXA-API-SIGNATURE'=>$signature];
    }
}
