<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PhoneVerificationOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_no_phone_shows_not_added_state(): void
    {
        $user = User::factory()->create([
            'phone' => null,
            'phone_country_code' => null,
            'phone_normalized' => null,
            'phone_verified_at' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/security/phone')
            ->assertOk()
            ->assertJsonPath('data.state', 'not_added')
            ->assertJsonPath('data.phone', null);
    }

    public function test_valid_phone_request_creates_otp_record(): void
    {
        config()->set('services.sms.provider', 'test');
        config()->set('services.sms.test.enabled', true);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/phone/request', [
                'country_code' => 'NG',
                'phone' => '08012345678',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Test mode: no SMS was sent.');

        $this->assertDatabaseHas('phone_verification_otps', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_configured_provider_without_delivery_returns_unavailable_without_creating_otp(): void
    {
        config()->set('services.sms.provider', 'twilio');
        config()->set('services.sms.twilio.sid', 'test-sid');
        config()->set('services.sms.twilio.token', 'test-token');
        config()->set('services.sms.twilio.from', '+15555550100');

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/phone/request', [
                'country_code' => 'NG',
                'phone' => '08012345678',
            ])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'SMS_DELIVERY_UNAVAILABLE');

        $this->assertDatabaseMissing('phone_verification_otps', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_wrong_otp_fails_verification(): void
    {
        config()->set('services.sms.provider', 'test');
        config()->set('services.sms.test.enabled', true);

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/phone/request', [
                'country_code' => 'NG',
                'phone' => '08012345678',
            ])->assertOk();

        $otp = PhoneVerificationOtp::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/phone/verify', [
                'otp' => '000000',
                'phone' => '08012345678',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_OTP');
    }

    public function test_correct_otp_marks_phone_as_verified(): void
    {
        config()->set('services.sms.provider', 'test');
        config()->set('services.sms.test.enabled', true);

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/phone/request', [
                'country_code' => 'NG',
                'phone' => '08012345678',
            ])->assertOk();

        $otpRecord = PhoneVerificationOtp::query()->where('user_id', $user->id)->firstOrFail();
        $otp = '123456';
        $otpRecord->update(['otp_hash' => Hash::make($otp)]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/phone/verify', [
                'otp' => $otp,
                'phone' => '08012345678',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertNotNull($user->phone_verified_at);
        $this->assertSame('+2348012345678', $user->phone_normalized);
        $this->assertSame('NG', $user->phone_country_code);
    }

    public function test_frontend_cannot_submit_fake_phone_verified_flag(): void
    {
        config()->set('services.sms.provider', 'test');
        config()->set('services.sms.test.enabled', true);

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/phone/request', [
                'country_code' => 'NG',
                'phone' => '08012345678',
            ])->assertOk();

        $otpRecord = PhoneVerificationOtp::query()->where('user_id', $user->id)->firstOrFail();
        $otp = '123456';
        $otpRecord->update(['otp_hash' => Hash::make($otp)]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/phone/verify', [
                'otp' => $otp,
                'phone' => '08012345678',
                'phone_verified' => true,
            ])
            ->assertOk();

        $user->refresh();
        $this->assertNotNull($user->phone_verified_at);
        $this->assertFalse((bool) ($user->getAttribute('phone_verified') ?? false));
    }

}
