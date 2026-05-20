<?php

namespace Database\Factories;

use App\Models\InternalAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalAccount>
 */
class InternalAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'account_type' => 'futures_wallet',
            'account_name' => 'Futures Trading Account',
            'available_balance' => $this->faker->randomFloat(2, 1000, 100000),
            'locked_balance' => $this->faker->randomFloat(2, 0, 10000),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
