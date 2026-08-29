<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FlightGameBet;
use App\Models\FlightGameRound;
use App\Models\FlightGameSetting;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AccountClosureSafetyService;
use App\Services\FlightGameRoundStateMachine;
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
        $this->enableRealMoneyFlight();
        $user = $this->verifiedUser();
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
        $this->enableRealMoneyFlight();
        $user = $this->verifiedUser();
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
        $this->enableRealMoneyFlight();
        $user = $this->verifiedUser();
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
        $this->enableRealMoneyFlight();
        $user = $this->verifiedUser();

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
        $this->enableRealMoneyFlight();
        $user = $this->verifiedUser();
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

    public function test_real_money_participation_is_disabled_by_default(): void
    {
        $user = $this->verifiedUser();
        $this->seedWalletState($user, '100.000000000000000000');

        $this->getJson('/api/games/flight/state')->assertOk();

        $response = $this->actingAs($user)->postJson('/api/games/flight/bets', [
            'asset' => 'USDT',
            'stake' => '10.00000000',
            'mode' => 'real',
        ], [
            'X-Idempotency-Key' => 'flight-real-disabled',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Real-money EXA Flight participation is disabled pending legal/regulatory approval.');
    }

    public function test_demo_participation_does_not_touch_canonical_ledger(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/games/flight/state')->assertOk();

        $response = $this->actingAs($user)->postJson('/api/games/flight/bets', [
            'asset' => 'USDT',
            'stake' => '10.00000000',
            'mode' => 'demo',
        ], [
            'X-Idempotency-Key' => 'flight-demo-entry',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'placed');

        $this->assertDatabaseHas('flight_game_bets', [
            'idempotency_key' => 'flight-demo-entry',
            'mode' => 'demo',
            'ledger_reference' => null,
        ]);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_self_exclusion_blocks_real_money_participation(): void
    {
        $this->enableRealMoneyFlight();
        $user = $this->verifiedUser();
        $this->seedWalletState($user, '100.000000000000000000');

        $this->actingAs($user)->postJson('/api/games/flight/responsible-gaming/self-exclusion', [
            'status' => 'SELF_EXCLUDED',
            'expires_at' => now()->addDay()->toISOString(),
            'reason_category' => 'USER_REQUEST',
        ])->assertCreated();

        $this->getJson('/api/games/flight/state')->assertOk();

        $response = $this->actingAs($user)->postJson('/api/games/flight/bets', [
            'asset' => 'USDT',
            'stake' => '10.00000000',
            'mode' => 'real',
        ], [
            'X-Idempotency-Key' => 'flight-self-excluded',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Responsible gaming restriction prevents new EXA Flight participation.');
    }

    public function test_treasury_coverage_rejects_excessive_real_money_exposure(): void
    {
        $this->enableRealMoneyFlight(['max_round_liability' => '5.00000000']);
        $user = $this->verifiedUser();
        $this->seedWalletState($user, '100.000000000000000000');

        $this->getJson('/api/games/flight/state')->assertOk();

        $response = $this->actingAs($user)->postJson('/api/games/flight/bets', [
            'asset' => 'USDT',
            'stake' => '10.00000000',
            'mode' => 'real',
        ], [
            'X-Idempotency-Key' => 'flight-treasury-reject',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'EXA Flight treasury risk limit rejected this entry.');
        $this->assertDatabaseHas('flight_game_risk_incidents', ['type' => 'TREASURY_RISK']);
    }

    public function test_account_closure_is_blocked_by_unresolved_flight_game_entry(): void
    {
        $user = User::factory()->create();

        $round = FlightGameRound::query()->create([
            'round_uuid' => (string) Str::uuid(),
            'round_number' => 1,
            'status' => 'betting',
            'mode' => 'demo',
            'asset' => 'USDT',
            'fairness_version' => 'exa-flight-v1',
            'server_seed_hash' => hash('sha256', 'seed'),
            'client_seed' => 'EXA-FLIGHT:1',
            'nonce' => 1,
            'crash_multiplier' => '2.00000000',
            'growth_rate' => '0.16000000',
            'betting_opens_at' => now()->subSecond(),
            'betting_closes_at' => now()->addSeconds(5),
            'starts_at' => now()->addSeconds(6),
            'crashes_at' => now()->addSeconds(30),
        ]);

        FlightGameBet::query()->create([
            'bet_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'round_id' => $round->id,
            'panel_slot' => 1,
            'mode' => 'demo',
            'asset' => 'USDT',
            'stake' => '10.000000000000000000',
            'status' => 'placed',
            'idempotency_key' => 'flight-closure-blocker',
            'placed_at' => now(),
        ]);

        $readiness = app(AccountClosureSafetyService::class)->readiness($user->id);

        $this->assertFalse($readiness['can_close']);
        $this->assertSame('UNRESOLVED_GAME_ENTRY', $readiness['blockers'][0]['type']);
    }

    public function test_real_money_entry_creates_and_consumes_canonical_reservation(): void
    {
        $this->enableRealMoneyFlight();
        $user = $this->verifiedUser();
        $this->seedWalletState($user, '100.000000000000000000');

        $this->getJson('/api/games/flight/state')->assertOk();

        $response = $this->actingAs($user)->postJson('/api/games/flight/bets', [
            'asset' => 'USDT',
            'stake' => '10.00000000',
            'mode' => 'real',
        ], [
            'X-Idempotency-Key' => 'flight-reservation-entry',
        ]);

        $response->assertCreated();
        $reservationId = $response->json('data.reservation_id');
        $this->assertNotEmpty($reservationId);

        $reservation = Reservation::query()->where('reservation_id', $reservationId)->firstOrFail();
        $this->assertSame(Reservation::STATUS_CONSUMED, $reservation->status);
        $this->assertSame('flight_game_entry', $reservation->purpose);
        $this->assertDatabaseCount('reservations', 1);

        $replay = $this->actingAs($user)->postJson('/api/games/flight/bets', [
            'asset' => 'USDT',
            'stake' => '10.00000000',
            'mode' => 'real',
        ], [
            'X-Idempotency-Key' => 'flight-reservation-entry',
        ]);

        $replay->assertOk();
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('flight_game_bets', 1);
    }

    public function test_demo_entry_creates_no_canonical_financial_reservation(): void
    {
        $user = User::factory()->create();
        $this->getJson('/api/games/flight/state')->assertOk();

        $this->actingAs($user)->postJson('/api/games/flight/bets', [
            'asset' => 'USDT',
            'stake' => '10.00000000',
            'mode' => 'demo',
        ], [
            'X-Idempotency-Key' => 'flight-no-reservation-demo',
        ])->assertCreated();

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_round_state_machine_rejects_invalid_transition(): void
    {
        $round = FlightGameRound::query()->create([
            'round_uuid' => (string) Str::uuid(),
            'round_number' => 1,
            'status' => 'betting',
            'round_state' => FlightGameRoundStateMachine::SCHEDULED,
            'mode' => 'demo',
            'asset' => 'USDT',
            'fairness_version' => 'exa-flight-v1',
            'server_seed_hash' => hash('sha256', 'seed'),
            'client_seed' => 'EXA-FLIGHT:1',
            'nonce' => 1,
            'crash_multiplier' => '2.00000000',
            'growth_rate' => '0.16000000',
            'betting_opens_at' => now(),
            'betting_closes_at' => now()->addSecond(),
            'starts_at' => now()->addSeconds(2),
            'crashes_at' => now()->addSeconds(5),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid EXA Flight round transition');

        app(FlightGameRoundStateMachine::class)->transition($round, FlightGameRoundStateMachine::SETTLED);
    }

    public function test_cancelled_round_refunds_real_money_locked_entry(): void
    {
        $this->enableRealMoneyFlight();
        $user = $this->verifiedUser();
        $this->seedWalletState($user, '100.000000000000000000');

        $this->getJson('/api/games/flight/state')->assertOk();
        $round = FlightGameRound::query()->latest('round_number')->firstOrFail();

        $this->actingAs($user)->postJson('/api/games/flight/bets', [
            'asset' => 'USDT',
            'stake' => '10.00000000',
            'mode' => 'real',
        ], [
            'X-Idempotency-Key' => 'flight-cancel-refund',
        ])->assertCreated();

        $this->assertAccountBalance($user->id, 'funding', 'USDT', '90.000000000000000000');
        $this->assertAccountBalance($user->id, 'game_locked', 'USDT', '10.000000000000000000');

        app(FlightGameService::class)->cancelRound((string) $round->round_uuid, $user->id, 'test_cancel');

        $this->assertAccountBalance($user->id, 'funding', 'USDT', '100.000000000000000000');
        $this->assertAccountBalance($user->id, 'game_locked', 'USDT', '0.000000000000000000');
        $this->assertDatabaseHas('flight_game_bets', [
            'idempotency_key' => 'flight-cancel-refund',
            'status' => 'cancelled',
        ]);
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

    private function enableRealMoneyFlight(array $overrides = []): void
    {
        $settings = array_merge([
            'game_mode' => 'real',
            'public_real_money_enabled' => true,
            'legal_real_money_approved' => true,
            'minimum_kyc_level' => 1,
            'jurisdiction_required' => true,
            'max_multiplier' => '2.00000000',
            'treasury_required_reserve' => '0.00000000',
            'max_round_liability' => '10000.00000000',
            'max_platform_exposure' => '25000.00000000',
        ], $overrides);

        foreach ($settings as $key => $value) {
            FlightGameSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    private function verifiedUser(): User
    {
        return User::factory()->create([
            'kyc_level' => 1,
            'kyc_verified_at' => now(),
            'verified_country' => 'NG',
            'residence_country' => 'NG',
            'account_status' => 'FULLY_ACTIVE',
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

