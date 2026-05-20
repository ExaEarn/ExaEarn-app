<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletBalance;
use App\Models\InternalWalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_transfer_success(): void
    {
        $user = User::factory()->create();

        // Check if table exists
        $this->assertTrue(\Schema::hasTable('wallet_balances'), 'wallet_balances table does not exist');

        // Create initial balances
        WalletBalance::create([
            'user_id' => $user->id,
            'wallet_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '1000.00',
        ]);

        WalletBalance::create([
            'user_id' => $user->id,
            'wallet_type' => 'spot',
            'asset' => 'USDT',
            'balance' => '0.00',
        ]);

        $payload = [
            'from_wallet' => 'funding',
            'to_wallet' => 'spot',
            'asset' => 'USDT',
            'amount' => '500.00',
        ];

        $response = $this->actingAs($user)->postJson('/api/wallet/internal-transfer', $payload);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Internal transfer completed.']);

        // Check balances updated
        $this->assertDatabaseHas('wallet_balances', [
            'user_id' => $user->id,
            'wallet_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '500.00',
        ]);

        $this->assertDatabaseHas('wallet_balances', [
            'user_id' => $user->id,
            'wallet_type' => 'spot',
            'asset' => 'USDT',
            'balance' => '500.00',
        ]);

        // Check transactions logged
        $this->assertDatabaseHas('internal_wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'transfer_out',
            'wallet_type' => 'funding',
            'asset' => 'USDT',
            'amount' => '500.00',
        ]);

        $this->assertDatabaseHas('internal_wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'transfer_in',
            'wallet_type' => 'spot',
            'asset' => 'USDT',
            'amount' => '500.00',
        ]);
    }

    public function test_internal_transfer_insufficient_balance(): void
    {
        $user = User::factory()->create();

        WalletBalance::create([
            'user_id' => $user->id,
            'wallet_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '100.00',
        ]);

        $payload = [
            'from_wallet' => 'funding',
            'to_wallet' => 'spot',
            'asset' => 'USDT',
            'amount' => '200.00',
        ];

        $response = $this->actingAs($user)->postJson('/api/wallet/internal-transfer', $payload);

        $response->assertStatus(422)
                 ->assertJson(['message' => 'Insufficient balance.']);
    }

    public function test_internal_transfer_same_wallet(): void
    {
        $user = User::factory()->create();

        $payload = [
            'from_wallet' => 'funding',
            'to_wallet' => 'funding',
            'asset' => 'USDT',
            'amount' => '100.00',
        ];

        $response = $this->actingAs($user)->postJson('/api/wallet/internal-transfer', $payload);

        $response->assertStatus(422)
                 ->assertJson(['message' => 'Cannot transfer to the same wallet type.']);
    }
}