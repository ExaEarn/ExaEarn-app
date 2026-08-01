<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'admin_id' => null,
            'type' => fake()->randomElement(['auth', 'wallet', 'trade', 'reward', 'staking', 'nft', 'security', 'system']),
            'action' => fake()->randomElement(['login', 'deposit', 'order_created', 'checkin_reward', 'stake']),
            'ip' => fake()->ipv4(),
            'device' => fake()->userAgent(),
            'data' => ['source' => 'factory'],
            'status' => fake()->randomElement(['success', 'failed', 'pending']),
        ];
    }
}
