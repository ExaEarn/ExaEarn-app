<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeveloperWebhookDelivery;
use App\Models\DeveloperWebhookEndpoint;
use App\Models\Role;
use App\Models\User;
use App\Services\DeveloperApiKeyService;
use App\Services\DeveloperProductionAccessService;
use App\Services\DeveloperWebhookService;
use App\Services\DeveloperWorkspaceService;
use App\Services\Security\DnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DeveloperFinalP1RemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(DnsResolver::class,new class extends DnsResolver {
            public function resolve(string $host): array { return ['93.184.216.34']; }
        });
    }

    public function test_webhook_command_dispatches_only_due_active_sandbox_deliveries(): void
    {
        Http::fake(['*'=>Http::response(['ok'=>true])]);
        [$project,$service]=$this->webhookProject();
        $service->enqueue($project,'order.filled',['order_id'=>'due'],'evt-due');
        $service->enqueue($project,'order.filled',['order_id'=>'future'],'evt-future');
        DeveloperWebhookDelivery::query()->where('event_id','evt-future')->update(['next_attempt_at'=>now()->addHour()]);

        $this->artisan('developer:webhooks:dispatch',['--limit'=>10])->assertSuccessful();

        $this->assertDatabaseHas('developer_webhook_deliveries',['event_id'=>'evt-due','status'=>'DELIVERED']);
        $this->assertDatabaseHas('developer_webhook_deliveries',['event_id'=>'evt-future','status'=>'PENDING']);
        Http::assertSentCount(1);
    }

    public function test_webhook_dispatch_fails_closed_for_disabled_endpoint_project_and_production_gate(): void
    {
        Http::fake(['*'=>Http::response(['ok'=>true])]);
        [$project,$service]=$this->webhookProject();
        $endpoint=DeveloperWebhookEndpoint::query()->firstOrFail();
        $service->enqueue($project,'order.filled',['order_id'=>'disabled'],'evt-disabled');
        $endpoint->update(['status'=>'disabled']);
        $service->deliverDue();
        $this->assertDatabaseHas('developer_webhook_deliveries',['event_id'=>'evt-disabled','status'=>'DEAD_LETTERED']);

        $endpoint->update(['status'=>'active']);
        $service->enqueue($project,'order.filled',['order_id'=>'archived'],'evt-archived');
        $project->update(['status'=>'archived']);
        $service->deliverDue();
        $this->assertDatabaseHas('developer_webhook_deliveries',['event_id'=>'evt-archived','status'=>'DEAD_LETTERED']);

        $project->update(['status'=>'active']);
        $project->environments()->where('type','production')->update(['status'=>'active']);
        $service->register($project,['url'=>'https://production.example.test/hook','events'=>['order.filled'],'environment'=>'production']);
        $service->enqueue($project,'order.filled',['order_id'=>'production'],'evt-production','production');
        config(['developer_api.webhooks.production_delivery_enabled'=>false,'developer_api.webhooks.production_egress_verified'=>false]);
        $service->deliverDue();
        $this->assertDatabaseHas('developer_webhook_deliveries',['event_id'=>'evt-production','status'=>'DEAD_LETTERED']);
        Http::assertSentCount(0);
    }

    public function test_terminal_production_access_requests_cannot_be_generically_reapproved(): void
    {
        [$project,$request]=$this->productionRequest('reject-case');$service=app(DeveloperProductionAccessService::class);$admin=$this->admin();
        $rejected=$service->decide($admin,$request,['action'=>'reject','idempotency_key'=>'reject','expected_version'=>1]);
        $this->expectException(RuntimeException::class);
        $service->decide($admin,$rejected,['action'=>'approve','capabilities'=>['account.read'=>'approved'],'idempotency_key'=>'revive-rejected','expected_version'=>2]);
    }

    public function test_revoke_clears_pending_second_review_and_requires_new_application(): void
    {
        [$project,$request]=$this->productionRequest('revoke-case',['wallet.transfer']);$service=app(DeveloperProductionAccessService::class);$first=$this->admin();$second=$this->admin();
        $pending=$service->decide($first,$request,['action'=>'approve','capabilities'=>['wallet.transfer'=>'approved'],'idempotency_key'=>'first','expected_version'=>1]);
        $this->assertSame('pending_second_review',$pending->capabilities->first()->status);
        $revoked=$service->decide($second,$pending,['action'=>'revoke','idempotency_key'=>'revoke','expected_version'=>2]);
        $this->assertSame('revoked',$revoked->capabilities->first()->status);
        try {
            $service->decide($second,$revoked,['action'=>'approve','capabilities'=>['wallet.transfer'=>'approved'],'idempotency_key'=>'revive','expected_version'=>3]);
            $this->fail('Revoked request was reusable.');
        } catch (RuntimeException) { $this->addToAssertionCount(1); }
        $new=$service->submit($project->user,$project,['use_case'=>'trading_application','capabilities'=>['account.read'],'idempotency_key'=>'new-after-revoke']);
        $this->assertSame('submitted',$new->status);
    }

    public function test_suspension_requires_explicit_resume_and_fresh_review(): void
    {
        [$project,$request]=$this->productionRequest('suspend-case');$service=app(DeveloperProductionAccessService::class);$admin=$this->admin();
        $approved=$service->decide($admin,$request,['action'=>'approve','capabilities'=>['account.read'=>'approved'],'idempotency_key'=>'approve','expected_version'=>1]);
        $suspended=$service->decide($admin,$approved,['action'=>'suspend','idempotency_key'=>'suspend','expected_version'=>2]);
        try {
            $service->decide($admin,$suspended,['action'=>'approve','capabilities'=>['account.read'=>'approved'],'idempotency_key'=>'unsafe-resume','expected_version'=>3]);
            $this->fail('Suspended request was generically approved.');
        } catch (RuntimeException) { $this->addToAssertionCount(1); }
        $resumed=$service->decide($admin,$suspended,['action'=>'resume','idempotency_key'=>'explicit-resume','expected_version'=>3]);
        $this->assertSame('under_review',$resumed->status);
        $this->assertSame('pending',$resumed->capabilities->first()->status);
        $this->assertSame('locked',$project->environments()->where('type','production')->value('status'));
    }

    public function test_organization_production_access_is_backend_blocked_without_canonical_owner_identity(): void
    {
        config(['developer_api.production_access.organization_enabled'=>false]);
        $owner=User::factory()->create(['account_status'=>'FULLY_ACTIVE','kyc_level'=>2,'verified_country'=>'NG']);
        $workspace=app(DeveloperWorkspaceService::class);$organization=$workspace->createOrganization($owner,'Blocked Org');
        $project=$workspace->provisionProject($owner,$organization->workspace,['name'=>'Org API']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ORGANIZATION_PRODUCTION_ACCESS_BLOCKED');
        app(DeveloperProductionAccessService::class)->submit($owner,$project,['use_case'=>'trading_application','capabilities'=>['account.read'],'idempotency_key'=>'org-block']);
    }

    public function test_network_policy_allows_only_realtime_to_exact_api_authority_port(): void
    {
        $policy=file_get_contents(base_path('../../infrastructure/developer-platform/kubernetes/network-policy.yaml'));
        $this->assertStringContainsString('name: realtime-authority-egress',$policy);
        $this->assertStringContainsString('app: api-gateway',$policy);
        $this->assertStringContainsString('port: 8080',$policy);
        $this->assertStringNotContainsString('0.0.0.0/0',$policy);
    }

    private function webhookProject(): array
    {
        $user=User::factory()->create();$project=app(DeveloperApiKeyService::class)->createProject($user->id,['name'=>'Dispatch','environment'=>'sandbox']);$service=app(DeveloperWebhookService::class);
        $service->register($project,['url'=>'https://sandbox.example.test/hook','events'=>['order.filled'],'environment'=>'sandbox']);
        return[$project,$service];
    }

    private function productionRequest(string $key,array $capabilities=['account.read']): array
    {
        $user=User::factory()->create(['account_status'=>'FULLY_ACTIVE','kyc_level'=>2,'verified_country'=>'NG','two_factor_enabled'=>true]);$workspace=app(DeveloperWorkspaceService::class);$project=$workspace->provisionProject($user,$workspace->ensurePersonalWorkspace($user),['name'=>'State '.uniqid()]);
        return[$project,app(DeveloperProductionAccessService::class)->submit($user,$project,['use_case'=>'trading_application','capabilities'=>$capabilities,'idempotency_key'=>$key])];
    }

    private function admin(): Admin
    {
        $role=Role::query()->firstOrCreate(['name'=>'super_admin'],['description'=>'Production reviewer']);$identity=User::factory()->create();
        return Admin::query()->create(['canonical_user_id'=>$identity->id,'name'=>'Reviewer','email'=>uniqid().'@example.test','password'=>'password','role_id'=>$role->id,'status'=>'active','two_factor_enabled'=>true]);
    }
}
