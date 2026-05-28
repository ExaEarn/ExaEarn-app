<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Balance;
use App\Services\PortfolioService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class WalletFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_wallet_provisioning_suppresses_portfolio_side_effects(): void
    {
        config()->set('wallet.assets', [
            'USD' => ['code' => 'USD'],
            'USDT' => ['code' => 'USDT'],
        ]);

        $portfolio = Mockery::mock(PortfolioService::class);
        $portfolio->shouldNotReceive('invalidateCache');
        $portfolio->shouldNotReceive('getUserPortfolioValue');
        $this->app->instance(PortfolioService::class, $portfolio);

        $user = User::factory()->create();

        app(WalletService::class)->provisionWalletsForUser($user);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'currency' => 'USD',
        ]);
        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'currency' => 'USDT',
        ]);
    }

    public function test_internal_transfer_updates_balances_and_ledger()
    {
        $user = User::factory()->create();
        $balance = Balance::create([
            'user_id' => $user->id,
            'asset' => 'USDT',
            'funding_available' => 100,
            'spot_available' => 0
        ]);

        $this->actingAs($user)
             ->postJson('/api/wallet/transfer', [
                 'asset' => 'USDT',
                 'from' => 'funding',
                 'to' => 'spot',
                 'amount' => 50
             ])
             ->assertStatus(200);

        $balance->refresh();
        $this->assertEquals(50, $balance->funding_available);
        $this->assertEquals(50, $balance->spot_available);
        
        $this->assertDatabaseHas('ledger_entries', [
            'user_id' => $user->id,
            'amount' => -50,
            'wallet_type' => 'funding'
        ]);
    }
}
