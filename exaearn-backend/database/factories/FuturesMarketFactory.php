<?php

namespace Database\Factories;

use App\Models\FuturesMarket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuturesMarket>
 */
class FuturesMarketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'symbol' => $this->faker->unique()->randomElement(['BTCUSD', 'ETHUSD', 'XRPUSD', 'LTCUSD']),
            'status' => 'active',
            'last_price' => $this->faker->randomFloat(8, 1000, 100000),
            'min_leverage' => 1,
            'max_leverage' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
