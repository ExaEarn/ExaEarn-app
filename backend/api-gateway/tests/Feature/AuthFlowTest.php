<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DailyCheckIn;
use App\Models\ExapointBalance;
use App\Models\InternalAccount;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('wallet.assets', [
            'NGN' => ['code' => 'NGN'],
            'USDT' => ['code' => 'USDT'],
            'BTC' => ['code' => 'BTC'],
        ]);
    }

    public function test_register_creates_full_exaearn_account_initialization(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ADA@EXAEARN.IO',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'unique_user_id', 'referral_code']]);

        $user = User::query()->where('email', 'ada@exaearn.io')->firstOrFail();

        $this->assertStringStartsWith('EXA-', (string) $user->unique_user_id);
        $this->assertNotEmpty($user->referral_code);
        $this->assertSame(3, Wallet::query()->where('user_id', $user->id)->count());
        $this->assertSame(4, InternalAccount::query()->where('user_id', $user->id)->count());
        $this->assertTrue(ExapointBalance::query()->where('user_id', $user->id)->exists());
        $this->assertTrue(DailyCheckIn::query()->where('user_id', $user->id)->exists());
    }

    public function test_register_rejects_existing_email_with_login_guidance(): void
    {
        User::factory()->create(['email' => 'member@exaearn.io']);

        $this->postJson('/api/register', [
            'name' => 'Existing Member',
            'email' => 'MEMBER@EXAEARN.IO',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'ACCOUNT_EXISTS')
            ->assertJsonPath('message', 'Account already exists. Please login.');
    }

    public function test_account_check_validates_signup_details_before_onboarding(): void
    {
        $this->postJson('/api/account/check', [
            'validate_credentials' => true,
            'name' => 'Grace Hopper',
            'email' => 'grace@exaearn.io',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertStatus(422);

        $this->postJson('/api/account/check', [
            'validate_credentials' => true,
            'name' => 'Grace Hopper',
            'email' => 'grace@exaearn.io',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Account details accepted. Continue onboarding.');
    }

    public function test_login_requires_existing_account_and_valid_password(): void
    {
        $this->postJson('/api/login', [
            'email' => 'missing@exaearn.io',
            'password' => 'StrongPass1!',
        ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'ACCOUNT_NOT_FOUND');

        $user = User::factory()->create([
            'email' => 'trader@exaearn.io',
            'password' => Hash::make('StrongPass1!'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'trader@exaearn.io',
            'password' => 'wrong-password',
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');

        $this->postJson('/api/login', [
            'email' => 'TRADER@EXAEARN.IO',
            'password' => 'StrongPass1!',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'user']);

        $this->assertAuthenticatedAs($user);
    }
}
