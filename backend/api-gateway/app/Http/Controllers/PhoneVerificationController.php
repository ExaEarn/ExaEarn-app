<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PhoneVerificationOtp;
use App\Models\User;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PhoneVerificationController extends Controller
{
    public function __construct(
        private readonly SecurityEventLogger $securityEventLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'state' => $user?->phone && $user?->phone_verified_at ? 'verified' : ($user?->phone ? 'pending' : 'not_added'),
                'phone' => $user?->phone ? $user->phone : null,
                'phone_country_code' => $user?->phone_country_code,
                'phone_normalized' => $user?->phone_normalized,
                'phone_verified' => (bool) $user?->phone_verified_at,
                'last_verified_at' => $user?->phone_verified_at?->toISOString(),
            ],
        ]);
    }

    public function requestOtp(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'country_code' => ['required', 'string', 'size:2'],
        ]);

        if (!$this->isProductionSmsConfigured()) {
            return response()->json([
                'success' => true,
                'message' => 'Phone verification is ready for setup; production SMS provider configuration is still required.',
                'code' => 'SMS_PROVIDER_REQUIRED',
                'data' => [
                    'provider' => config('services.sms.provider'),
                ],
            ]);
        }

        if (config('services.sms.provider') !== 'test') {
            return response()->json([
                'success' => false,
                'message' => 'SMS delivery is unavailable for the configured provider.',
                'code' => 'SMS_DELIVERY_UNAVAILABLE',
            ], 503);
        }

        $normalized = $this->normalizePhone((string) $validated['phone'], (string) $validated['country_code']);
        $existingUser = $this->findUserWithPhone($normalized);

        if ($existingUser && (int) $existingUser->id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'This phone number is already linked to another ExaEarn account.',
                'code' => 'PHONE_ALREADY_IN_USE',
            ], 409);
        }

        $otp = (string) random_int(100000, 999999);
        $otpHash = Hash::make($otp);
        $expiresAt = now()->addMinutes(10);

        PhoneVerificationOtp::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'expired'])
            ->update(['status' => 'expired']);

        $otpRecord = PhoneVerificationOtp::query()->create([
            'user_id' => $user->id,
            'phone' => $normalized,
            'country_code' => strtoupper((string) $validated['country_code']),
            'otp_hash' => $otpHash,
            'otp_length' => 6,
            'sent_at' => now(),
            'expires_at' => $expiresAt,
            'attempt_count' => 0,
            'resend_count' => 0,
            'status' => 'pending',
            'metadata' => [
                'delivery_mode' => config('services.sms.provider', 'test'),
                'phone_raw' => trim((string) $validated['phone']),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $this->securityEventLogger->logSensitiveAction((int) $user->id, 'phone_verification_requested', $request->ip(), [
            'phone_normalized' => $normalized,
            'country_code' => strtoupper((string) $validated['country_code']),
            'record_id' => $otpRecord->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Test mode: no SMS was sent.',
            'data' => [
                'expires_at' => $expiresAt->toISOString(),
                'otp_length' => 6,
            ],
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'digits:6'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();
        $otpRecord = PhoneVerificationOtp::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest('expires_at')
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'No active verification code was found.',
                'code' => 'OTP_NOT_FOUND',
            ], 404);
        }

        if ($otpRecord->expires_at->isPast()) {
            $otpRecord->update(['status' => 'expired']);

            return response()->json([
                'success' => false,
                'message' => 'This verification code has expired.',
                'code' => 'OTP_EXPIRED',
            ], 422);
        }

        if ($otpRecord->attempt_count >= 5) {
            $otpRecord->update(['status' => 'max_attempts']);

            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts. Request a new code.',
                'code' => 'OTP_MAX_ATTEMPTS',
            ], 429);
        }

        if (!Hash::check((string) $validated['otp'], $otpRecord->otp_hash)) {
            $otpRecord->increment('attempt_count');

            return response()->json([
                'success' => false,
                'message' => 'The one-time code you entered is incorrect.',
                'code' => 'INVALID_OTP',
            ], 422);
        }

        $normalizedPhone = $this->normalizePhone((string) ($validated['phone'] ?? $otpRecord->phone), (string) $otpRecord->country_code);

        if ($normalizedPhone !== $otpRecord->phone) {
            return response()->json([
                'success' => false,
                'message' => 'The entered phone number does not match the active verification request.',
                'code' => 'PHONE_MISMATCH',
            ], 422);
        }

        $user->forceFill([
            'phone' => $normalizedPhone,
            'phone_country_code' => strtoupper((string) $otpRecord->country_code),
            'phone_normalized' => $normalizedPhone,
            'phone_verified_at' => now(),
        ])->save();

        $otpRecord->update([
            'status' => 'verified',
            'verified_at' => now(),
            'metadata' => array_merge((array) $otpRecord->metadata, ['verified_phone' => $normalizedPhone]),
        ]);

        $this->securityEventLogger->logSensitiveAction((int) $user->id, 'phone_verified', $request->ip(), [
            'phone_normalized' => $normalizedPhone,
            'country_code' => strtoupper((string) $otpRecord->country_code),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Phone number verified successfully.',
            'data' => [
                'phone' => $normalizedPhone,
                'phone_verified' => true,
            ],
        ]);
    }

    private function isProductionSmsConfigured(): bool
    {
        $provider = config('services.sms.provider');

        if ($provider === 'test') {
            return (bool) config('services.sms.test.enabled', true);
        }

        if ($provider === 'twilio') {
            return !empty(config('services.sms.twilio.sid')) && !empty(config('services.sms.twilio.token')) && !empty(config('services.sms.twilio.from'));
        }

        if ($provider === 'vonage') {
            return !empty(config('services.sms.vonage.api_key')) && !empty(config('services.sms.vonage.api_secret')) && !empty(config('services.sms.vonage.from'));
        }

        return false;
    }

    private function normalizePhone(string $phone, string $countryCode): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($countryCode === 'NG' && strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        $countryPrefix = match ($countryCode) {
            'NG' => '234',
            'US' => '1',
            default => '',
        };

        if ($countryPrefix !== '' && !str_starts_with($digits, $countryPrefix)) {
            $digits = $countryPrefix . $digits;
        }

        return '+' . ltrim($digits, '+');
    }

    private function findUserWithPhone(string $normalized): ?User
    {
        return User::query()->where('phone_normalized', $normalized)->first();
    }

}
