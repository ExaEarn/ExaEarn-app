<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GiftCardSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends Factory<GiftCardSubmission>
 */
class GiftCardSubmissionFactory extends Factory
{
    protected $model = GiftCardSubmission::class;

    public function definition(): array
    {
        $code = $this->faker->numerify('############');

        return [
            'user_id' => User::factory(),
            'brand' => 'amazon',
            'card_value' => '50.00',
            'currency' => 'USD',
            'card_hash' => hash('sha256', $code),
            'encrypted_card_code' => Crypt::encryptString($code),
            'encrypted_card_pin' => Crypt::encryptString($this->faker->numerify('####')),
            'status' => 'pending',
            'payout_amount' => null,
            'rate_applied' => null,
            'verification_data' => [],
            'metadata' => [],
        ];
    }
}
