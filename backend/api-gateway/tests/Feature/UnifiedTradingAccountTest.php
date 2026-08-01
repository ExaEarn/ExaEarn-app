<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccountTransfer;
use App\Models\InternalAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedTradingAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_funding_and_unified_trading_accounts(): void
    {
        $user = User::factory()->create();

        WalletBalance::query()->create([
            'user_id' => $user->id,
            'wallet_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '250.00000000',
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '100.00000000',
            'locked_balance' => '25.00000000',
        ]);

        InternalAccount::query()->create([
            'user_id' => $user->id,
            'account_type' => 'futures_wallet',
            'account_name' => 'Futures Wallet',
            'available_balance' => '40.00000000',
            'locked_balance' => '60.00000000',
        ]);

        $response = $this->actingAs($user)->getJson('/api/accounts');

        $response->assertOk()
            ->assertJsonPath('data.accounts.0.key', 'funding')
            ->assertJsonPath('data.accounts.1.key', 'unified_trading');

        $assetRow = collect($response->json('data.assets'))->firstWhere('asset', 'USDT');
        $this->assertNotNull($assetRow);
        $this->assertSame('250.00000000', $assetRow['funding']['available']);
        $this->assertSame('140.00000000', $assetRow['unified_trading']['transferable']);
    }

    public function test_it_transfers_from_funding_to_unified_trading(): void
    {
        $user = User::factory()->create();

        WalletBalance::query()->create([
            'user_id' => $user->id,
            'wallet_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '1000.00000000',
        ]);

        $response = $this->actingAs($user)->postJson('/api/accounts/transfer', [
            'from_account' => 'funding',
            'to_account' => 'unified_trading',
            'asset' => 'USDT',
            'amount' => '500.00000000',
            'idempotency_key' => 'unified-transfer-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.from_account', 'funding')
            ->assertJsonPath('data.to_account', 'unified_trading')
            ->assertJsonPath('data.amount', '500.000000000000000000');

        $this->assertDatabaseHas('wallet_balances', [
            'user_id' => $user->id,
            'wallet_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '500.00000000',
        ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '500.00000000',
            'locked_balance' => '0.00000000',
        ]);

        $this->assertDatabaseHas('account_transfers', [
            'user_id' => $user->id,
            'from_account' => 'funding',
            'to_account' => 'unified_trading',
            'asset' => 'USDT',
        ]);
    }

    public function test_it_transfers_from_unified_trading_back_to_funding_using_spot_and_futures_available_balances(): void
    {
        $user = User::factory()->create();

        WalletBalance::query()->create([
            'user_id' => $user->id,
            'wallet_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '0.00000000',
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '300.00000000',
            'locked_balance' => '150.00000000',
        ]);

        InternalAccount::query()->create([
            'user_id' => $user->id,
            'account_type' => 'futures_wallet',
            'account_name' => 'Futures Wallet',
            'available_balance' => '200.00000000',
            'locked_balance' => '250.00000000',
        ]);

        $response = $this->actingAs($user)->postJson('/api/accounts/transfer', [
            'from_account' => 'unified_trading',
            'to_account' => 'funding',
            'asset' => 'USDT',
            'amount' => '450.00000000',
            'idempotency_key' => 'unified-transfer-2',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '0.00000000',
            'locked_balance' => '150.00000000',
        ]);

        $this->assertDatabaseHas('internal_accounts', [
            'user_id' => $user->id,
            'account_type' => 'futures_wallet',
            'available_balance' => '50.00000000',
            'locked_balance' => '250.00000000',
        ]);

        $this->assertDatabaseHas('wallet_balances', [
            'user_id' => $user->id,
            'wallet_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '450.00000000',
        ]);
    }

    public function test_it_rejects_transfers_above_unified_transferable_balance_and_keeps_idempotency_safe(): void
    {
        $user = User::factory()->create();

        WalletBalance::query()->create([
            'user_id' => $user->id,
            'wallet_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '1000.00000000',
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '100.00000000',
            'locked_balance' => '40.00000000',
        ]);

        InternalAccount::query()->create([
            'user_id' => $user->id,
            'account_type' => 'futures_wallet',
            'account_name' => 'Futures Wallet',
            'available_balance' => '25.00000000',
            'locked_balance' => '80.00000000',
        ]);

        $failed = $this->actingAs($user)->postJson('/api/accounts/transfer', [
            'from_account' => 'unified_trading',
            'to_account' => 'funding',
            'asset' => 'USDT',
            'amount' => '200.00000000',
            'idempotency_key' => 'unified-transfer-3',
        ]);

        $failed->assertStatus(422)
            ->assertJsonPath('message', 'Transfer amount exceeds Unified Trading transferable balance.');

        $first = $this->actingAs($user)->postJson('/api/accounts/transfer', [
            'from_account' => 'funding',
            'to_account' => 'unified_trading',
            'asset' => 'USDT',
            'amount' => '120.00000000',
            'idempotency_key' => 'unified-transfer-4',
        ]);

        $second = $this->actingAs($user)->postJson('/api/accounts/transfer', [
            'from_account' => 'funding',
            'to_account' => 'unified_trading',
            'asset' => 'USDT',
            'amount' => '120.00000000',
            'idempotency_key' => 'unified-transfer-4',
        ]);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame(
            $first->json('data.reference'),
            $second->json('data.reference'),
        );

        $this->assertSame(1, AccountTransfer::query()->where('idempotency_key', 'unified-transfer-4')->count());
        $this->assertDatabaseHas('wallet_balances', [
            'user_id' => $user->id,
            'wallet_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '880.00000000',
        ]);
    }
}
