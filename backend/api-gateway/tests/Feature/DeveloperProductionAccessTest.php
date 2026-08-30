<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeveloperProductionAccessRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DeveloperApiKeyService;
use App\Services\DeveloperProductionAccessService;
use App\Services\DeveloperWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DeveloperProductionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandbox_stays_available_without_kyc_while_production_submission_requires_it(): void
    {
        [$user,$project]=$this->project(['kyc_level'=>0]);
        $sandbox=app(DeveloperApiKeyService::class)->createKey($user->id,$project,['name'=>'Sandbox','permissions'=>['account.read']]);
        $this->assertStringStartsWith('exa_test_',$sandbox['api_key']);
        $this->expectException(RuntimeException::class);
        $this->submit($user,$project,['account.read']);
    }

    public function test_submission_is_idempotent_reuses_canonical_kyc_and_restricted_products_stay_restricted(): void
    {
        [$user,$project]=$this->project();
        $first=$this->submit($user,$project,['account.read','futures.trade','wallet.withdraw'],'same-request');
        $second=$this->submit($user,$project,['account.read','futures.trade','wallet.withdraw'],'same-request');
        $this->assertTrue($first->is($second));
        $this->assertSame(1,DeveloperProductionAccessRequest::query()->count());
        $this->assertSame('pending',$first->capabilities->firstWhere('capability','account.read')->status);
        $this->assertSame('restricted',$first->capabilities->firstWhere('capability','futures.trade')->status);
        $this->assertSame('restricted',$first->capabilities->firstWhere('capability','wallet.withdraw')->status);
    }

    public function test_partial_approval_activates_environment_but_key_scopes_remain_capability_limited(): void
    {
        [$user,$project]=$this->project();$request=$this->submit($user,$project,['account.read','futures.trade']);$admin=$this->admin();
        $reviewed=app(DeveloperProductionAccessService::class)->decide($admin,$request,['action'=>'partial_approve','capabilities'=>['account.read'=>'approved','futures.trade'=>'approved'],'idempotency_key'=>'review-1','expected_version'=>1]);
        $this->assertSame('partially_approved',$reviewed->status);
        $this->assertSame('restricted',$reviewed->capabilities->firstWhere('capability','futures.trade')->status);
        $this->assertSame('active',$project->environments()->where('type','production')->value('status'));
        $created=app(DeveloperApiKeyService::class)->createKey($user->id,$project->fresh(),['name'=>'Production read','environment'=>'production','permissions'=>['account.read']]);
        $this->assertStringStartsWith('exa_live_',$created['api_key']);
        try{app(DeveloperApiKeyService::class)->createKey($user->id,$project->fresh(),['name'=>'Escalation','environment'=>'production','permissions'=>['futures.trade']]);$this->fail('Unapproved scope was issued.');}catch(RuntimeException){$this->addToAssertionCount(1);}
    }

    public function test_revocation_removes_effective_permission_immediately_and_is_retry_safe(): void
    {
        [$user,$project]=$this->project();$request=$this->submit($user,$project,['account.read']);$admin=$this->admin();$service=app(DeveloperProductionAccessService::class);
        $approved=$service->decide($admin,$request,['action'=>'approve','capabilities'=>['account.read'=>'approved'],'idempotency_key'=>'approve']);
        $this->assertSame(['account.read'],$service->approvedCapabilities($project->id));
        $revoked=$service->decide($admin,$approved,['action'=>'revoke','idempotency_key'=>'revoke']);
        $retried=$service->decide($admin,$revoked,['action'=>'revoke','idempotency_key'=>'revoke']);
        $this->assertSame($revoked->id,$retried->id);
        $this->assertSame([],$service->approvedCapabilities($project->id));
        $this->expectException(RuntimeException::class);$service->assertCapabilities($project->fresh()->load(['environments','organization','user']),['account.read']);
    }

    public function test_review_cannot_grant_an_unrequested_capability(): void
    {
        [$user,$project]=$this->project();$request=$this->submit($user,$project,['account.read']);
        $this->expectException(RuntimeException::class);
        app(DeveloperProductionAccessService::class)->decide($this->admin(),$request,['action'=>'approve','capabilities'=>['spot.trade'=>'approved'],'idempotency_key'=>'bad-review']);
    }

    private function project(array $attributes=[]): array
    {
        $user=User::factory()->create($attributes+['account_status'=>'FULLY_ACTIVE','kyc_level'=>2,'verified_country'=>'NG','two_factor_enabled'=>true]);
        $workspaces=app(DeveloperWorkspaceService::class);$workspace=$workspaces->ensurePersonalWorkspace($user);
        return[$user,$workspaces->provisionProject($user,$workspace,['name'=>'Production access test'])];
    }

    private function submit(User $user,$project,array $capabilities,string $key='submission')
    {
        return app(DeveloperProductionAccessService::class)->submit($user,$project,['use_case'=>'trading_application','capabilities'=>$capabilities,'idempotency_key'=>$key]);
    }

    private function admin(): Admin
    {
        $role=Role::query()->create(['name'=>'super_admin','description'=>'Test reviewer']);
        $identity=User::factory()->create();
        return Admin::query()->create(['canonical_user_id'=>$identity->id,'name'=>'Reviewer','email'=>uniqid().'@example.test','password'=>'password','role_id'=>$role->id,'status'=>'active','two_factor_enabled'=>true]);
    }
}
