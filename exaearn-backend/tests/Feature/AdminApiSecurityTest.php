<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_creates_token_and_session(): void
    {
        $role = Role::query()->create(['name' => 'admin']);

        Admin::query()->create([
            'name' => 'Ops Admin',
            'email' => 'ops-admin@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'ops-admin@example.com',
            'password' => 'StrongPassword123!',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'admin' => ['id', 'email'],
            ]);

        $this->assertDatabaseHas('admin_sessions', [
            'admin_id' => (int) $response->json('admin.id'),
        ]);
    }

    public function test_admin_users_endpoint_requires_permission(): void
    {
        $role = Role::query()->create(['name' => 'admin']);

        $admin = Admin::query()->create([
            'name' => 'Restricted Admin',
            'email' => 'restricted-admin@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertStatus(403)
            ->assertJsonPath('permission', 'users.view');
    }

    public function test_admin_users_endpoint_allows_authorized_admin_and_writes_audit_log(): void
    {
        $role = Role::query()->create(['name' => 'admin']);
        $permission = Permission::query()->create(['name' => 'users.view']);
        $role->permissions()->attach($permission->id);

        $admin = Admin::query()->create([
            'name' => 'Authorized Admin',
            'email' => 'authorized-admin@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertOk();

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'admin.action',
        ]);
    }

    public function test_admin_wallet_adjust_endpoint_accepts_wallet_id(): void
    {
        $role = Role::query()->create(['name' => 'admin']);
        $permission = Permission::query()->create(['name' => 'wallet.adjust']);
        $role->permissions()->attach($permission->id);

        $admin = Admin::query()->create([
            'name' => 'Wallet Admin',
            'email' => 'wallet-admin@example.com',
            'password' => Hash::make('StrongPassword123!'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);

        $user = \App\Models\User::factory()->create();
        $wallet = \App\Models\Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '100.00000000',
            'locked_balance' => '0.00000000',
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/wallets/adjust', [
                'wallet_id' => $wallet->id,
                'asset' => 'USDT',
                'amount' => '10',
                'reason' => 'Manual balance correction',
                'confirmation' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount', '10');

        $this->assertDatabaseHas('ledger_entries', [
            'user_id' => $user->id,
            'asset' => 'USDT',
        ]);
    }
}

