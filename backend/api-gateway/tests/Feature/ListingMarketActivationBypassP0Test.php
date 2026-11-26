<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Market;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\MarketActivationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ListingMarketActivationBypassP0Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security-ratelimit.enabled' => false]);
    }

    public function test_ordinary_authenticated_user_cannot_create_active_market(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/trade/markets', [
            'symbol' => 'PWN/USDT', 'base_currency' => 'PWN', 'quote_currency' => 'USDT', 'status' => 'active',
        ])->assertForbidden();

        $this->assertDatabaseMissing('markets', ['symbol' => 'PWN/USDT']);
    }

    public function test_legacy_admin_create_is_permissioned_and_cannot_accept_active_fields(): void
    {
        $unprivileged = $this->admin('restricted-market-admin@example.com', false);
        $this->actingAs($unprivileged)->postJson('/api/admin/trading', [
            'symbol' => 'SAFE/USDT', 'base_currency' => 'SAFE', 'quote_currency' => 'USDT',
        ])->assertForbidden();

        $admin = $this->admin('market-admin@example.com', true);
        $this->actingAs($admin)->postJson('/api/admin/trading', [
            'symbol' => 'SAFE/USDT', 'base_currency' => 'SAFE', 'quote_currency' => 'USDT', 'status' => 'active', 'trading_status' => 'TRADING',
        ])->assertUnprocessable();

        $this->actingAs($admin)->postJson('/api/admin/trading', [
            'symbol' => 'SAFE/USDT', 'base_currency' => 'SAFE', 'quote_currency' => 'USDT',
        ])->assertCreated()->assertJsonPath('data.status', 'pre_launch')->assertJsonPath('data.trading_status', 'PRE_LAUNCH');
    }

    public function test_generic_internal_configuration_cannot_forge_authority_or_activate(): void
    {
        $market = app(MarketActivationService::class)->createConfiguration([
            'symbol' => 'GUARD/USDT', 'base_currency' => 'GUARD', 'quote_currency' => 'USDT',
            'status' => 'active', 'trading_status' => 'TRADING', 'governance_source' => 'SYSTEM_MANAGED',
        ]);

        $this->assertSame('pre_launch', $market->status);
        $this->assertSame('PRE_LAUNCH', $market->trading_status);
        $this->assertSame(MarketActivationService::UNKNOWN_REVIEW_REQUIRED, $market->governance_source);
        $this->expectException(\RuntimeException::class);
        app(MarketActivationService::class)->assertTradeable($market);
    }

    public function test_listing_authority_cannot_be_claimed_without_canonical_application(): void
    {
        $this->expectException(AuthorizationException::class);
        app(MarketActivationService::class)->createConfiguration([
            'symbol' => 'FORGED/USDT', 'base_currency' => 'FORGED', 'quote_currency' => 'USDT',
        ], MarketActivationService::LISTING_GOVERNED);
    }

    public function test_pre_launch_market_rejects_order_entry(): void
    {
        $market = Market::query()->create([
            'symbol' => 'WAIT/USDT', 'base_currency' => 'WAIT', 'quote_currency' => 'USDT',
            'status' => 'pre_launch', 'trading_status' => 'PRE_LAUNCH',
        ]);

        $this->expectExceptionMessage('Market is not active for trading.');
        app(MarketActivationService::class)->assertTradeable($market);
    }

    private function admin(string $email, bool $permitted): Admin
    {
        $role = Role::query()->create(['name' => $permitted ? 'market-manager-'.uniqid() : 'restricted-'.uniqid()]);
        if ($permitted) {
            $permission = Permission::query()->firstOrCreate(['name' => 'trade.manage']);
            $role->permissions()->attach($permission->id);
        }
        return Admin::query()->create(['name' => 'Market Admin', 'email' => $email, 'password' => Hash::make('StrongPassword123!'),
            'role_id' => $role->id, 'status' => 'active', 'two_factor_enabled' => true]);
    }
}
