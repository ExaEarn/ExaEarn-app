<?php

namespace Tests\Feature;

use App\Models\CheckinStreak;
use App\Models\DailyCheckin;
use App\Models\TradingCredit;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyRewardEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_claim_daily_reward_once_per_day(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this
            ->withHeader('X-Device-Fingerprint', 'device-a')
            ->postJson('/api/checkin');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['reward_points', 'current_streak', 'available_points']]);

        $this->assertDatabaseCount('daily_checkins', 1);

        $this
            ->withHeader('X-Device-Fingerprint', 'device-a')
            ->postJson('/api/checkin')
            ->assertStatus(409)
            ->assertJsonPath('status', 'error');
    }

    public function test_mystery_box_requires_seven_day_streak_and_resets_after_opening(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        CheckinStreak::create([
            'user_id' => $user->id,
            'current_streak' => 7,
            'highest_streak' => 7,
            'last_checkin_date' => now()->toDateString(),
        ]);

        $response = $this
            ->withHeader('X-Device-Fingerprint', 'device-b')
            ->postJson('/api/mystery-box/open');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.streak_reset', true)
            ->assertJsonPath('data.current_streak', 0);

        $this->assertDatabaseCount('mystery_boxes', 1);
        $this->assertSame(0, CheckinStreak::where('user_id', $user->id)->value('current_streak'));
    }

    public function test_redeem_creates_locked_non_withdrawable_trading_credit(): void
    {
        $user = User::factory()->create([
            'created_at' => now()->subDays(8),
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        UserPoint::create([
            'user_id' => $user->id,
            'total_points' => 5000,
            'available_points' => 5000,
            'lifetime_points' => 5000,
        ]);

        $response = $this
            ->withHeader('X-Device-Fingerprint', 'device-c')
            ->postJson('/api/redeem');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.points_used', 5000)
            ->assertJsonPath('data.credit_amount', 5)
            ->assertJsonPath('data.locked', true)
            ->assertJsonPath('data.available_points', 0);

        $credit = TradingCredit::first();
        $this->assertNotNull($credit);
        $this->assertTrue($credit->locked);
        $this->assertSame('0.00000000', $credit->withdrawable_profit);
    }

    public function test_progress_returns_sustainable_redemption_estimate(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        UserPoint::create([
            'user_id' => $user->id,
            'total_points' => 1340,
            'available_points' => 1340,
            'lifetime_points' => 1340,
        ]);

        DailyCheckin::create([
            'user_id' => $user->id,
            'reward_points' => 3,
            'streak_day' => 1,
            'checkin_date' => now()->toDateString(),
        ]);

        $this->getJson('/api/points')
            ->assertOk()
            ->assertJsonPath('data.available_points', 1340)
            ->assertJsonPath('data.redemption_target_points', 5000)
            ->assertJsonPath('data.redemption_value_usdt', 5);
    }
}
