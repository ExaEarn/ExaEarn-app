<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\VerifyEntryTransactionJob;
use App\Models\User;
use App\Services\BlockchainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GameFiFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_lottery_game(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(BlockchainService::class)
            ->shouldReceive('createLotteryRound')
            ->once()
            ->andReturn([
                'round_id' => 1,
                'contract_address' => '0xgame',
                'tx_hash' => '0xcreate',
            ]);

        $response = $this->actingAs($admin)->postJson('/api/gamefi/lotteries', [
            'name' => 'Mega Jackpot',
            'entry_fee_eth' => 0.01,
            'max_players' => 100,
            'trigger_type' => 'max_players',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('lottery_games', ['name' => 'Mega Jackpot']);
    }

    public function test_lottery_join_is_queued_for_verification(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'user', 'created_at' => now()->subDays(5)]);
        $game = \App\Models\LotteryGame::query()->create([
            'game_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'contract_round_id' => 1,
            'name' => 'Daily Draw',
            'entry_fee_eth' => 0.01,
            'max_players' => 10,
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->postJson("/api/gamefi/lotteries/{$game->id}/join", [
            'wallet_address' => '0xabc1230000000000000000000000000000000001',
            'entry_tx_hash' => '0xentry1',
            'entry_amount_eth' => 0.01,
        ]);

        $response->assertAccepted();
        Queue::assertPushed(VerifyEntryTransactionJob::class);
    }

    public function test_admin_can_create_betting_pool(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(BlockchainService::class)
            ->shouldReceive('createBettingPool')
            ->once()
            ->andReturn([
                'pool_id' => 2,
                'contract_address' => '0xgame',
                'tx_hash' => '0xpool',
            ]);

        $response = $this->actingAs($admin)->postJson('/api/gamefi/betting-pools', [
            'event_name' => 'Team A vs Team B',
            'bet_options' => ['Team A', 'Team B', 'Draw'],
            'entry_fee_eth' => 0,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('betting_pools', ['event_name' => 'Team A vs Team B']);
    }
}
