<?php

namespace App\Services;

use App\Models\LoginDevice;
use App\Models\User;
use App\Repositories\UserRepository;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class AuthService
{
    public function __construct(
        protected UserRepository $users,
        protected Google2FA $google2fa
    ) {
    }

    public function register(array $data): array
    {
        $data['referral_code'] = $data['referral_code'] ?? $this->generateReferralCode();

        $user = $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'referral_code' => $data['referral_code'],
        ]);

        $user->sendEmailVerificationNotification();

        return [
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken,
        ];
    }

    public function attemptLogin(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    public function issueLoginToken(User $user): string
    {
        return $user->createToken('auth_token')->plainTextToken;
    }

    public function recordLoginDevice(User $user, Request $request): bool
    {
        $deviceName = $request->input('device_name');

        if (! $deviceName) {
            $deviceName = $this->guessDeviceName((string) $request->userAgent());
        }

        $attributes = [
            'user_id' => $user->id,
            'device_name' => $deviceName,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        $device = LoginDevice::query()->firstOrCreate($attributes);
        $device->last_login_at = now();
        $device->save();

        if ($device->wasRecentlyCreated) {
            // Optional: hook an email / notification here for new device logins.
            Log::info('New login device detected', [
                'user_id' => $user->id,
                'device_name' => $deviceName,
                'ip' => $request->ip(),
            ]);
        }

        return $device->wasRecentlyCreated;
    }

    public function generateTwoFactorSetup(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();
        $qrUrl = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);

        $renderer = new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd());
        $writer = new Writer($renderer);
        $svg = $writer->writeString($qrUrl);

        $user->two_factor_secret = Crypt::encryptString($secret);
        $user->two_factor_enabled = false;
        $user->save();

        return [
            'secret' => $secret,
            'qr_code' => 'data:image/svg+xml;base64,'.base64_encode($svg),
        ];
    }

    public function confirmTwoFactor(User $user, string $otp): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        if (! $this->google2fa->verifyKey($secret, $otp)) {
            return false;
        }

        $user->two_factor_enabled = true;
        $user->save();

        return true;
    }

    public function verifyTwoFactorLogin(User $user, string $otp): bool
    {
        if (! $user->two_factor_enabled || ! $user->two_factor_secret) {
            return false;
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        return $this->google2fa->verifyKey($secret, $otp);
    }

    public function createPendingTwoFactorToken(User $user): string
    {
        $token = Str::random(64);
        Cache::put($this->pendingTwoFactorKey($user->id, $token), true, now()->addMinutes(5));

        return $token;
    }

    public function isPendingTwoFactorTokenValid(int $userId, string $token): bool
    {
        $key = $this->pendingTwoFactorKey($userId, $token);

        // Do NOT consume the pending token here.
        // Otherwise a single wrong OTP attempt would invalidate the pending 2FA token.
        return Cache::get($key, false) === true;
    }

    public function consumePendingTwoFactorToken(int $userId, string $token): void
    {
        Cache::forget($this->pendingTwoFactorKey($userId, $token));
    }

    protected function pendingTwoFactorKey(int $userId, string $token): string
    {
        return "two_factor_pending:{$userId}:{$token}";
    }

    protected function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    protected function guessDeviceName(string $userAgent): string
    {
        if ($userAgent === '') {
            return 'Unknown device';
        }

        return Str::limit($userAgent, 120, '');
    }
}
