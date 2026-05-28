<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_load_and_update_language_region_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/preferences/language-region')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.language', 'English (Default)')
            ->assertJsonPath('data.region', 'Nigeria');

        $this->actingAs($user)
            ->patchJson('/api/preferences/language-region', [
                'language' => 'Yoruba',
                'region' => 'Ghana',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.language', 'Yoruba')
            ->assertJsonPath('data.region', 'Ghana');

        $this->assertSame('Yoruba', $user->fresh()->preferences['language_region']['language']);
    }

    public function test_user_can_load_and_update_currency_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/preferences/currency')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.displayCurrency', 'USD')
            ->assertJsonPath('data.transactionCurrency', 'USD');

        $this->actingAs($user)
            ->patchJson('/api/preferences/currency', [
                'displayCurrency' => 'NGN',
                'transactionCurrency' => 'USDT',
            ])
            ->assertUnprocessable();

        $this->actingAs($user)
            ->patchJson('/api/preferences/currency', [
                'displayCurrency' => 'NGN',
                'transactionCurrency' => 'BTC',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.displayCurrency', 'NGN')
            ->assertJsonPath('data.transactionCurrency', 'BTC');

        $fresh = $user->fresh();
        $this->assertSame('NGN', $fresh->preferences['currency_preference']['display_currency']);
        $this->assertSame('BTC', $fresh->preferences['currency_preference']['transaction_currency']);
    }
}
