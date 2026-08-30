<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\Verify2FA;
use App\Models\User;
use App\Notifications\DeveloperPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class DeveloperAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_request_is_generic_for_existing_and_unknown_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'developer@example.com']);

        $existing = $this->postJson('/api/forgot-password', ['email' => $user->email]);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'unknown@example.com']);

        $existing->assertOk()->assertJsonPath('message', 'If an account exists for that email, reset instructions have been sent.');
        $unknown->assertOk()->assertJsonPath('message', 'If an account exists for that email, reset instructions have been sent.');
        Notification::assertSentTo($user, DeveloperPasswordResetNotification::class);
    }

    public function test_reset_token_is_one_time_and_reset_revokes_api_sessions(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $user->createToken('developer-session');
        $token = Password::broker()->createToken($user);
        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewStrong#Password1',
            'password_confirmation' => 'NewStrong#Password1',
        ];

        $this->postJson('/api/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('NewStrong#Password1', (string) $user->fresh()->password));
        $this->assertCount(0, $user->fresh()->tokens);
        $this->postJson('/api/reset-password', $payload)->assertStatus(422)->assertJsonPath('code', 'INVALID_RESET_TOKEN');
    }

    public function test_sensitive_developer_credential_action_requires_recent_authentication(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->postJson('/api/developer/projects/999/keys', [])
            ->assertStatus(428)
            ->assertJsonPath('error.code', 'RECENT_AUTH_REQUIRED');

        $this->actingAs($user)->withSession(['auth_recent_at' => time()])
            ->postJson('/api/developer/projects/999/keys', [])
            ->assertNotFound();
    }

    public function test_reauthentication_requires_totp_for_a_two_factor_user(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->create([
            'password' => Hash::make('Current#Password1'),
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);

        $this->actingAs($user)->postJson('/api/auth/reauthenticate', [
            'password' => 'Current#Password1', 'two_factor_code' => '000000',
        ])->assertStatus(422)->assertJsonPath('code', 'REAUTHENTICATION_FAILED');

        $this->actingAs($user)->postJson('/api/auth/reauthenticate', [
            'password' => 'Current#Password1', 'two_factor_code' => $this->totp($secret),
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $token = $owner->createToken('owner-session')->accessToken;

        $this->actingAs($attacker)->withSession(['auth_recent_at' => time()])
            ->deleteJson('/api/auth/sessions/'.$token->id)
            ->assertNotFound();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->id]);
    }

    private function totp(string $secret): string
    {
        $reflection = new \ReflectionClass(Verify2FA::class);
        $method = $reflection->getMethod('base32Decode');
        $key = $method->invoke(null, $secret);
        $counter = intdiv(time(), 30);
        $binary = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binary, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24) | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8) | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
