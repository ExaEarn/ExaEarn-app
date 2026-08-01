<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FlightGameBet;
use App\Models\FlightGameRound;
use App\Models\User;
use App\Services\FlightGameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FlightGameTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_endpoint_creates_a_live_round(): void
    {
        $response = $this->getJson('/api/games/flight/state');

        $response->assertOk()
            ->assertJsonPath('data.round.status', 'betting');

        $this->assertDatabaseCount('flight_game_rounds', 1);
    }

    public function test_place_bet_moves_funds_from_funding_to_game_locked(): void
    {
        $user = User::factory()->create();
        $this->seedWalletState($user, '100.000000000000000000');

        $this->getJson('/api/games/flight/state')->assertOk();

        $response = $this->actingAs($user)->postJson('/api/games/flight/bets', [
            'asset' => 'USDT',
            'stake' => '10.00000000',
            'panel_slot' => 1,
            'auto_cashout' => '2.00',
        ], [
            'X-Idempotency-Key' => 'flight-test-bet-1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'placed');

        $this->assertAccountBalance($user->id, 'funding', 'USDT', '90.000000000000000000');
        $this->assertAccountBalance($user->id, 'game_locked', 'USDT', '10.000000000000000000');
    }

    public function test_duplicate_bet_idempotency_does_not_double_lock_funds(): void
    {
        $user = User::factory()->create();
        $this->seedWalletState($user, '100.000000000000000000');

        $this->getJson('/api/games/flight/state')->assertOk();

        $payload = [
            'asset' => 'USDT',
            'stake' => '12.50000000',
            'panel_slot' => 2,
            'auto_cashout' => '2.50',
        ];

        $first = $this->actingAs($user)->postJson('/api/games/flight/bets', $payload, [
            'X-Idempotency-Key' => 'flight-test-bet-idempotent',
        ]);
        $second = $this->actingAs($user)->postJson('/api/games/flight/bets', $payload, [
            'X-Idempotency-Key' => 'flight-test-bet-idempotent',
        ]);

        $first->assertCreated();
        $second->assertOk();
        $this->assertSame(
            $first->json('data.bet_uuid'),
            $second->json('data.bet_uuid')
        );

        $this->assertDatabaseCount('flight_game_bets', 1);
        $this->assertAccountBalance($user->id, 'funding', 'USDT', '87.500000000000000000');
        $this->assertAccountBalance($user->id, 'game_locked', 'USDT', '12.500000000000000000');
    }

    public function test_place_bet_is_rejected_after_betting_window_closes(): void
    {
        $user = User::factory()->create();
        $this->seedWalletState($user, '50.000000000000000000');

        $this->getJson('/api/games/flight/state')->assertOk();
        $round = FlightGameRound::query()->latest('round_number')->firstOrFail();
        $round->update([
            'betting_closes_at' => now()->subSecond(),
            'starts_at' => now()->subSecond(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/games/flight/bets', [
            'asset' => 'USDT',
            'stake' => '5.00000000',
            'panel_slot' => 1,
        ], [
            'X-Idempotency-Key' => 'flight-test-late-bet',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'This round is no longer accepting entries.');
    }

    public function test_cashout_moves_locked_funds_and_profit_back_to_funding(): void
    {
        $user = User::factory()->create();

        $round = FlightGameRound::query()->create([
            'round_uuid' => (string) Str::uuid(),
            'round_number' => 1,
            'status' => 'running',
            'mode' => 'real',
            'asset' => 'USDT',
            'fairness_version' => 'exa-flight-v1',
            'server_seed_hash' => hash('sha256', 'seed'),
            'client_seed' => 'EXA-FLIGHT:1',
            'nonce' => 1,
            'crash_multiplier' => '5.00000000',
            'growth_rate' => '0.16000000',
            'betting_opens_at' => now()->subSeconds(10),
            'betting_closes_at' => now()->subSeconds(5),
            'starts_at' => now()->subSeconds(4),
            'crashes_at' => now()->addSeconds(20),
        ]);

        Account::query()->create([
            'user_id' => $user->id,
            'account_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '0.000000000000000000',
        ]);

        Account::query()->create([
            'user_id' => $user->id,
            'account_type' => 'game_locked',
            'asset' => 'USDT',
            'balance' => '10.000000000000000000',
        ]);

        Account::query()->create([
            'user_id' => null,
            'account_type' => 'game_treasury',
            'asset' => 'USDT',
            'balance' => '1000.000000000000000000',
        ]);

        $bet = FlightGameBet::query()->create([
            'bet_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'round_id' => $round->id,
            'panel_slot' => 1,
            'mode' => 'real',
            'asset' => 'USDT',
            'stake' => '10.000000000000000000',
            'status' => 'placed',
            'idempotency_key' => 'flight-test-cashout-1',
            'placed_at' => now()->subSeconds(5),
            'metadata' => ['display_name' => $user->name],
        ]);

        $response = $this->actingAs($user)->postJson("/api/games/flight/bets/{$bet->bet_uuid}/cashout");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cashed_out');

        $funding = Account::query()->where('user_id', $user->id)->where('account_type', 'funding')->where('asset', 'USDT')->firstOrFail();
        $locked = Account::query()->where('user_id', $user->id)->where('account_type', 'game_locked')->where('asset', 'USDT')->firstOrFail();

        $this->assertTrue((float) $funding->balance > 10.0);
        $this->assertSame('0.000000000000000000', $locked->balance);
    }

    public function test_auto_cashout_executes_server_side_during_tick(): void
    {
        $user = User::factory()->create();
        $this->seedWalletState($user, '100.000000000000000000');

        $this->getJson('/api/games/flight/state')->assertOk();
        $betResponse = $this->actingAs($user)->postJson('/api/games/flight/bets', [
            'asset' => 'USDT',
            'stake' => '10.00000000',
            'panel_slot' => 1,
            'auto_cashout' => '1.20',
        ], [
            'X-Idempotency-Key' => 'flight-test-auto-cashout',
        ]);
        $betResponse->assertCreated();

        $round = FlightGameRound::query()->latest('round_number')->firstOrFail();
        $round->update([
            'status' => 'running',
            'starts_at' => now()->subSeconds(2),
            'crashes_at' => now()->addSeconds(15),
        ]);

        app(FlightGameService::class)->tick();

        $bet = FlightGameBet::query()->where('idempotency_key', 'flight-test-auto-cashout')->firstOrFail();
        $bet->refresh();

        $this->assertSame('cashed_out', $bet->status);
        $this->assertSame('1.20000000', $bet->cashout_multiplier);
        $this->assertAccountBalance($user->id, 'game_locked', 'USDT', '0.000000000000000000');

        $funding = Account::query()->where('user_id', $user->id)->where('account_type', 'funding')->where('asset', 'USDT')->firstOrFail();
        $this->assertTrue((float) $funding->balance > 100.0);
    }

    private function seedWalletState(User $user, string $fundingBalance): void
    {
        Account::query()->create([
            'user_id' => $user->id,
            'account_type' => 'funding',
            'asset' => 'USDT',
            'balance' => $fundingBalance,
        ]);

        Account::query()->create([
            'user_id' => $user->id,
            'account_type' => 'game_locked',
            'asset' => 'USDT',
            'balance' => '0.000000000000000000',
        ]);

        Account::query()->create([
            'user_id' => null,
            'account_type' => 'game_treasury',
            'asset' => 'USDT',
            'balance' => '100000.000000000000000000',
        ]);
    }

    private function assertAccountBalance(int $userId, string $accountType, string $asset, string $expected): void
    {
        $account = Account::query()
            ->where('user_id', $userId)
            ->where('account_type', $accountType)
            ->where('asset', $asset)
            ->firstOrFail();

        $this->assertSame($expected, $account->balance);
    }
}

