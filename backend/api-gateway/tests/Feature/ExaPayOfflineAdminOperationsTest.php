<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ExaPayOfflineAdminChangeApproval;
use App\Models\Permission;
use App\Models\Role;
use App\Services\RecentAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class ExaPayOfflineAdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'security.admin.require_2fa' => true,
            'app.env' => 'testing',
            'exapay_offline.allowed_environments' => ['testing'],
        ]);
    }

    public function test_view_permission_is_read_only_and_management_is_denied(): void
    {
        $role = Role::query()->create(['name' => 'exapay_offline_readonly_test']);
        $view = Permission::query()->firstOrCreate(['name' => 'exapay.offline.view']);
        $role->permissions()->attach($view->id);
        $admin = $this->admin($role);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/exapay/offline/overview')
            ->assertOk()
            ->assertJsonPath('data.simulation_only', true)
            ->assertJsonPath('data.real_funds_enabled', false);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/exapay/offline/risk-policy', [])
            ->assertForbidden()
            ->assertJsonPath('permission', 'exapay.offline.manage');
    }

    public function test_admin_cannot_approve_its_own_dual_control_change(): void
    {
        $role = Role::query()->create(['name' => 'super_admin']);
        $admin = $this->admin($role);
        $approvalUuid = (string) Str::uuid();
        ExaPayOfflineAdminChangeApproval::query()->create([
            'approval_uuid' => $approvalUuid,
            'change_type' => 'risk_policy',
            'proposed_change' => ['enabled' => false],
            'change_digest' => hash('sha256', '{"enabled":false}'),
            'idempotency_key' => 'admin-approval-self-test-0001',
            'requested_by_admin_id' => $admin->id,
            'status' => 'PENDING',
            'expires_at' => now()->addMinutes(10),
        ]);

        $recentAuth = Mockery::mock(RecentAuthenticationService::class);
        $recentAuth->shouldReceive('isRecent')->once()->andReturn(true);
        $this->app->instance(RecentAuthenticationService::class, $recentAuth);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/exapay/offline/risk-policy/approvals/{$approvalUuid}/approve")
            ->assertForbidden()
            ->assertJsonPath('message', 'A different administrator must approve this policy change.');

        $this->assertDatabaseHas('exapay_offline_admin_change_approvals', [
            'approval_uuid' => $approvalUuid,
            'status' => 'PENDING',
            'approved_by_admin_id' => null,
        ]);
    }

    public function test_distinct_admin_can_approve_a_pending_simulation_policy_change(): void
    {
        $role = Role::query()->create(['name' => 'exapay_offline_dual_control_test']);
        $manage = Permission::query()->firstOrCreate(['name' => 'exapay.offline.manage']);
        $role->permissions()->attach($manage->id);
        $requester = $this->admin($role);
        $approver = $this->admin($role);
        $change = ['enabled' => false, 'trust_epoch' => 1];
        $approvalUuid = (string) Str::uuid();
        ExaPayOfflineAdminChangeApproval::query()->create([
            'approval_uuid' => $approvalUuid,
            'change_type' => 'risk_policy',
            'proposed_change' => $change,
            'change_digest' => hash('sha256', json_encode($change, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'idempotency_key' => 'admin-approval-distinct-test-0001',
            'requested_by_admin_id' => $requester->id,
            'status' => 'PENDING',
            'expires_at' => now()->addMinutes(10),
        ]);

        $recentAuth = Mockery::mock(RecentAuthenticationService::class);
        $recentAuth->shouldReceive('isRecent')->once()->andReturn(true);
        $this->app->instance(RecentAuthenticationService::class, $recentAuth);

        $this->actingAs($approver, 'sanctum')
            ->postJson("/api/admin/exapay/offline/risk-policy/approvals/{$approvalUuid}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->assertDatabaseHas('exapay_offline_admin_change_approvals', [
            'approval_uuid' => $approvalUuid,
            'status' => 'APPROVED',
            'requested_by_admin_id' => $requester->id,
            'approved_by_admin_id' => $approver->id,
        ]);
    }

    public function test_recent_authentication_service_accepts_admin_identity(): void
    {
        $admin = $this->admin(Role::query()->create(['name' => 'exapay_offline_recent_auth_test']));

        $this->assertFalse(app(RecentAuthenticationService::class)->isRecent(Request::create('/admin/test'), $admin, 300));
    }

    private function admin(Role $role): Admin
    {
        return Admin::query()->create([
            'name' => 'Offline Safety Reviewer',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-only-password',
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
