<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user registration creates activity log
     */
    public function test_registration_logs_activity(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Audit Test User',
            'email' => 'audit@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'type' => 'auth',
            'action' => 'register',
            'status' => 'success',
        ]);
    }

    /**
     * Test login creates activity log
     */
    public function test_login_logs_activity(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('SecurePassword123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'SecurePassword123!',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'type' => 'auth',
            'action' => 'login',
            'status' => 'success',
        ]);
    }

    /**
     * Test failed login creates activity log
     */
    public function test_failed_login_logs_activity(): void
    {
        $user = User::factory()->create([
            'email' => 'failed@example.com',
            'password' => Hash::make('SecurePassword123!'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'failed@example.com',
            'password' => 'WrongPassword!',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'type' => 'auth',
            'action' => 'login_failed',
            'status' => 'failed',
        ]);
    }

    /**
     * Test logout creates activity log
     */
    public function test_logout_logs_activity(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/logout');

        $response->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'type' => 'auth',
            'action' => 'logout',
            'status' => 'success',
        ]);
    }

    /**
     * Test activity audit service logs correctly
     */
    public function test_activity_audit_service_logs_user_activity(): void
    {
        $user = User::factory()->create();

        $service = app(ActivityAuditService::class);
        $service->logWallet($user->id, 'deposit', [
            'amount' => 100,
            'asset' => 'USDT',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'type' => 'wallet',
            'action' => 'deposit',
            'data' => json_encode(['amount' => 100, 'asset' => 'USDT']),
        ]);
    }

    /**
     * Test admin logs are created
     */
    public function test_admin_logs_activity(): void
    {
        $service = app(ActivityAuditService::class);

        $service->logAdmin(1, 'adjust_balance', [
            'user_id' => 123,
            'adjustment' => 1000,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'admin_id' => 1,
            'type' => 'admin',
            'action' => 'adjust_balance',
        ]);
    }

    /**
     * Test system logs are created
     */
    public function test_system_logs_activity(): void
    {
        $service = app(ActivityAuditService::class);

        $service->logSystem('database_migration', [
            'migration' => 'test_migration',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => null,
            'admin_id' => null,
            'type' => 'system',
            'action' => 'database_migration',
        ]);
    }

    /**
     * Test trade logs
     */
    public function test_trade_logs_activity(): void
    {
        $user = User::factory()->create();
        $service = app(ActivityAuditService::class);

        $service->logTrade($user->id, 'order_created', [
            'pair' => 'BTC/USD',
            'price' => 50000,
            'amount' => 0.5,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'type' => 'trade',
            'action' => 'order_created',
        ]);
    }

    /**
     * Test reward logs
     */
    public function test_reward_logs_activity(): void
    {
        $user = User::factory()->create();
        $service = app(ActivityAuditService::class);

        $service->logReward($user->id, 'checkin_reward', [
            'amount' => 10,
            'asset' => 'EXA',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'type' => 'reward',
            'action' => 'checkin_reward',
        ]);
    }

    /**
     * Test staking logs
     */
    public function test_staking_logs_activity(): void
    {
        $user = User::factory()->create();
        $service = app(ActivityAuditService::class);

        $service->logStaking($user->id, 'stake', [
            'pool_id' => 1,
            'amount' => 1000,
            'duration' => 30,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'type' => 'staking',
            'action' => 'stake',
        ]);
    }

    /**
     * Test NFT logs
     */
    public function test_nft_logs_activity(): void
    {
        $user = User::factory()->create();
        $service = app(ActivityAuditService::class);

        $service->logNft($user->id, 'mint', [
            'nft_id' => 123,
            'collection' => 'ExaEarn Genesis',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'type' => 'nft',
            'action' => 'mint',
        ]);
    }

    /**
     * Test security logs
     */
    public function test_security_logs_activity(): void
    {
        $user = User::factory()->create();
        $service = app(ActivityAuditService::class);

        $service->logSecurity($user->id, 'password_changed', [
            'email' => $user->email,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'type' => 'security',
            'action' => 'password_changed',
        ]);
    }

    /**
     * Test user can view own activity logs
     */
    public function test_user_can_view_own_activity_logs(): void
    {
        $user = User::factory()->create();
        ActivityLog::factory(5)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/logs/my-activity');

        $response->assertOk()
            ->assertJsonPath('pagination.total', 5);
    }

    /**
     * Test user cannot view other user's logs
     */
    public function test_user_cannot_view_other_users_logs(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        ActivityLog::factory(5)->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2)
            ->getJson('/api/logs/my-activity');

        $response->assertOk()
            ->assertJsonPath('pagination.total', 0);
    }

    /**
     * Test activity log summary
     */
    public function test_user_can_view_activity_summary(): void
    {
        $user = User::factory()->create();
        ActivityLog::factory(3)->create([
            'user_id' => $user->id,
            'type' => 'auth',
        ]);
        ActivityLog::factory(2)->create([
            'user_id' => $user->id,
            'type' => 'wallet',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/logs/summary');

        $response->assertOk()
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.by_type.auth', 3)
            ->assertJsonPath('data.by_type.wallet', 2);
    }

    /**
     * Test pagination
     */
    public function test_activity_logs_pagination(): void
    {
        $user = User::factory()->create();
        ActivityLog::factory(50)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/logs/my-activity?page=1&per_page=20');

        $response->assertOk()
            ->assertJsonPath('pagination.total', 50)
            ->assertJsonPath('pagination.count', 20)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.last_page', 3);
    }

    /**
     * Test filtering by type
     */
    public function test_activity_logs_filter_by_type(): void
    {
        $user = User::factory()->create();
        ActivityLog::factory(3)->create(['user_id' => $user->id, 'type' => 'auth']);
        ActivityLog::factory(2)->create(['user_id' => $user->id, 'type' => 'wallet']);

        $response = $this->actingAs($user)
            ->getJson('/api/logs/my-activity?type=auth');

        $response->assertOk()
            ->assertJsonPath('pagination.total', 3);
    }

    /**
     * Test filtering by status
     */
    public function test_activity_logs_filter_by_status(): void
    {
        $user = User::factory()->create();
        ActivityLog::factory(3)->create([
            'user_id' => $user->id,
            'status' => 'success',
        ]);
        ActivityLog::factory(2)->create([
            'user_id' => $user->id,
            'status' => 'failed',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/logs/my-activity?status=failed');

        $response->assertOk()
            ->assertJsonPath('pagination.total', 2);
    }

    /**
     * Test admin can view all logs
     */
    public function test_admin_can_view_all_activity_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ActivityLog::factory(10)->create();

        $response = $this->actingAs($admin)
            ->getJson('/admin/logs/activity');

        $response->assertOk()
            ->assertJsonPath('pagination.total', 10);
    }

    /**
     * Test admin can view specific user logs
     */
    public function test_admin_can_view_specific_user_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        ActivityLog::factory(5)->create(['user_id' => $user->id]);
        ActivityLog::factory(3)->create();

        $response = $this->actingAs($admin)
            ->getJson("/admin/logs/user/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('pagination.total', 5);
    }

    /**
     * Test admin can view suspicious activity
     */
    public function test_admin_can_view_suspicious_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ActivityLog::factory(5)->create([
            'status' => 'failed',
            'type' => 'auth',
        ]);
        ActivityLog::factory(3)->create(['status' => 'success']);

        $response = $this->actingAs($admin)
            ->getJson('/admin/logs/suspicious');

        $response->assertOk();
    }

    /**
     * Test admin can view IP activity
     */
    public function test_admin_can_view_ip_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ActivityLog::factory(3)->create(['ip' => '192.168.1.1']);
        ActivityLog::factory(2)->create(['ip' => '192.168.1.2']);

        $response = $this->actingAs($admin)
            ->getJson('/admin/logs/ip-activity?ip=192.168.1.1');

        $response->assertOk()
            ->assertJsonPath('summary.total_activities', 3);
    }

    /**
     * Test IP and device are logged
     */
    public function test_ip_and_device_are_logged(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            ])
            ->getJson('/api/logs/my-activity');

        $response->assertOk();

        $log = ActivityLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log->ip);
        $this->assertNotNull($log->device);
    }

    /**
     * Test JSON data is stored correctly
     */
    public function test_json_data_is_stored_correctly(): void
    {
        $user = User::factory()->create();
        $service = app(ActivityAuditService::class);

        $data = [
            'amount' => 100,
            'asset' => 'USDT',
            'transaction_id' => 'txn_123',
        ];

        $service->logWallet($user->id, 'deposit', $data);

        $log = ActivityLog::where('user_id', $user->id)->first();
        $this->assertEquals($data, $log->data);
    }

    /**
     * Test logs cannot be deleted
     */
    public function test_activity_logs_cannot_be_deleted(): void
    {
        $log = ActivityLog::factory()->create();
        $id = $log->id;

        // Attempt to delete should not work
        $log->delete();

        // Log should still exist
        $this->assertDatabaseHas('activity_logs', ['id' => $id]);
    }

    /**
     * Test logs have immutable timestamps
     */
    public function test_activity_logs_have_correct_timestamps(): void
    {
        $service = app(ActivityAuditService::class);
        $user = User::factory()->create();

        $service->logWallet($user->id, 'deposit', []);

        $log = ActivityLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log->created_at);
        $this->assertNull($log->updated_at);
    }
}
