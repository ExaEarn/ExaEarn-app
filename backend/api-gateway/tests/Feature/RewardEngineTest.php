<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RewardActivity;
use App\Models\User;
use App\Models\UserReward;
use App\Services\RewardEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_check_in_creates_one_reward_per_day(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $first = $this->actingAs($user)->postJson('/api/rewards/check-in');
        $first->assertCreated();

        $second = $this->actingAs($user)->postJson('/api/rewards/check-in');
        $second->assertStatus(422);

        $this->assertDatabaseCount('user_rewards', 1);
        $this->assertDatabaseHas('user_rewards', [
            'user_id' => $user->id,
            'activity_type' => 'daily_check_in',
            'status' => 'claimed',
            'reward_token' => 'EXAPOINT',
        ]);
    }

    public function test_home_daily_check_in_endpoint_matches_frontend_contract(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $first = $this
            ->actingAs($user)
            ->withHeader('X-Device-Fingerprint', 'device-home')
            ->postJson('/api/checkin');

        $first->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    'reward_points',
                    'current_streak',
                    'available_points',
                    'progress' => [
                        'available_points',
                        'current_streak',
                        'redemption_target_points',
                    ],
                ],
            ]);

        $this->actingAs($user)->getJson('/api/points')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.current_streak', 1);

        $this->actingAs($user)->getJson('/api/checkin/history')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data.checkins');

        $this
            ->actingAs($user)
            ->withHeader('X-Device-Fingerprint', 'device-home')
            ->postJson('/api/checkin')
            ->assertStatus(409)
            ->assertJsonPath('status', 'error');
    }

    public function test_formula_reward_uses_activity_rate_and_daily_limit(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        /** @var RewardEngineService $engine */
        $engine = app(RewardEngineService::class);
        $reward = $engine->issueReward($user->id, 'trade_volume', '1000', [
            'activity_key' => 'trade-1',
        ]);

        $this->assertSame('1.00000000', number_format((float) $reward->reward_amount, 8, '.', ''));
    }

    public function test_claim_is_disabled_for_token_distribution(): void
    {
        $user = User::factory()->create();
        RewardActivity::query()->create([
            'activity_type' => 'daily_check_in',
            'reward_rate' => '1',
            'daily_limit' => '1',
            'status' => 'active',
            'mode' => 'fixed',
            'min_activity_value' => '1',
        ]);

        $reward = UserReward::query()->create([
            'user_id' => $user->id,
            'activity_type' => 'daily_check_in',
            'activity_value' => '1',
            'reward_amount' => '1',
            'reward_token' => 'EXA',
            'status' => 'approved',
            'activity_key' => now()->toDateString(),
            'validated_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/rewards/{$reward->id}/claim", [
            'wallet_address' => '0x1111111111111111111111111111111111111111',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'ExaToken reward distribution is disabled. Rewards are now issued as ExaPoints instantly.',
        ]);
    }

    public function test_blocked_reward_is_created_for_suspicious_activity(): void
    {
        $existing = User::factory()->create();
        $existing->loginDevices()->create([
            'device_name' => 'web',
            'user_agent' => 'TestAgent',
            'ip_address' => '127.0.0.1',
            'fingerprint_hash' => hash('sha256', 'device-1'),
            'last_login_at' => now(),
        ]);

        $user = User::factory()->create();
        $user->loginDevices()->create([
            'device_name' => 'web',
            'user_agent' => 'TestAgent',
            'ip_address' => '127.0.0.1',
            'fingerprint_hash' => hash('sha256', 'device-1'),
            'last_login_at' => now(),
        ]);

        /** @var RewardEngineService $engine */
        $engine = app(RewardEngineService::class);
        $reward = $engine->issueReward($user->id, 'trade_volume', '100', [
            'activity_key' => 'trade-risk-1',
            'ip_address' => '127.0.0.1',
            'fingerprint_hash' => hash('sha256', 'device-1'),
        ]);

        $this->assertSame('blocked', $reward->status);
        $this->assertNotNull($user->fresh()->reward_suspended_until);
    }
}
