<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Models\Admin;use App\Models\DeveloperApiRequestLog;use App\Models\DeveloperProductionCapabilityReview;use App\Models\Role;use App\Models\User;use App\Services\DeveloperApiKeyService;use App\Services\DeveloperProductionAccessService;use App\Services\DeveloperWorkspaceService;use Illuminate\Auth\Access\AuthorizationException;use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Support\Facades\Route;use Tests\TestCase;
class DeveloperGovernanceWave2Test extends TestCase
{
 use RefreshDatabase;
 public function test_requester_and_same_canonical_reviewer_conflicts_fail_closed():void
 {
  [$user,$project,$request]=$this->request(['account.read']);$admin=$this->admin($user);
  $this->expectException(AuthorizationException::class);app(DeveloperProductionAccessService::class)->decide($admin,$request,['action'=>'approve','capabilities'=>['account.read'=>'approved'],'idempotency_key'=>'self']);
 }
 public function test_high_risk_capability_requires_two_independent_reviewers_and_revokes_immediately():void
 {
  [$user,$project,$request]=$this->request(['account.read','wallet.transfer']);$a=$this->admin();$b=$this->admin();$service=app(DeveloperProductionAccessService::class);
  $first=$service->decide($a,$request,['action'=>'partial_approve','capabilities'=>['account.read'=>'approved','wallet.transfer'=>'approved'],'idempotency_key'=>'first','expected_version'=>1]);
  $this->assertSame('pending_second_review',$first->capabilities->firstWhere('capability','wallet.transfer')->status);$this->assertNotContains('wallet.transfer',$service->approvedCapabilities($project->id));
  try{$service->decide($a,$first,['action'=>'approve','capabilities'=>['wallet.transfer'=>'approved'],'idempotency_key'=>'duplicate','expected_version'=>2]);$this->fail('Same reviewer satisfied dual control.');}catch(AuthorizationException){$this->addToAssertionCount(1);}
  $second=$service->decide($b,$first->fresh(),['action'=>'approve','capabilities'=>['wallet.transfer'=>'approved'],'idempotency_key'=>'second','expected_version'=>2]);
  $wallet=$second->capabilities->firstWhere('capability','wallet.transfer');
  $this->assertSame('approved',$wallet->status);$this->assertContains('wallet.transfer',$service->approvedCapabilities($project->id));$this->assertSame(2,DeveloperProductionCapabilityReview::query()->where('capability_id',$wallet->id)->where('decision','approved')->count());
  $revoked=$service->decide($a,$second,['action'=>'revoke','idempotency_key'=>'revoke','expected_version'=>3]);$this->assertSame('revoked',$revoked->status);$this->assertNotContains('wallet.transfer',$service->approvedCapabilities($project->id));
 }
 public function test_request_logging_uses_key_environment_and_records_exception_once_without_secrets():void
 {
  Route::middleware(['developer.context'])->get('/api/developer-test/failure',fn()=>throw new \RuntimeException('database secret should not leak'));
  $response=$this->getJson('/api/developer-test/failure',['EXA-API-SECRET'=>'never-log','EXA-API-SIGNATURE'=>'never-log']);$response->assertStatus(500)->assertHeader('X-Exa-Request-Id');
  $log=DeveloperApiRequestLog::query()->where('path','/api/developer-test/failure')->sole();$this->assertSame(500,$log->status_code);$this->assertSame('INTERNAL_ERROR',$log->error_code);$this->assertStringNotContainsString('never-log',json_encode($log->toArray()));$this->assertStringNotContainsString('database secret',json_encode($log->toArray()));
 }
 private function request(array $capabilities):array
 {
  $user=User::factory()->create(['account_status'=>'FULLY_ACTIVE','kyc_level'=>2,'verified_country'=>'NG','two_factor_enabled'=>true]);$w=app(DeveloperWorkspaceService::class);$project=$w->provisionProject($user,$w->ensurePersonalWorkspace($user),['name'=>'Governance']);$request=app(DeveloperProductionAccessService::class)->submit($user,$project,['use_case'=>'trading_application','capabilities'=>$capabilities,'idempotency_key'=>'submit']);return[$user,$project,$request];
 }
 private function admin(?User $identity=null):Admin
 {
  $role=Role::query()->firstOrCreate(['name'=>'super_admin'],['description'=>'Governance reviewer']);$identity??=User::factory()->create();return Admin::query()->create(['canonical_user_id'=>$identity->id,'name'=>'Reviewer','email'=>uniqid().'@example.test','password'=>'password','role_id'=>$role->id,'status'=>'active','two_factor_enabled'=>true]);
 }
}
