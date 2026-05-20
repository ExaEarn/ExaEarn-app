<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LoginDevice;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\FraudDetectionService;
use App\Services\RateLimiterService;
use App\Services\ReferralService;
use App\Services\UserInitializationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService,
        private readonly UserInitializationService $userInitializationService,
        private readonly RateLimiterService $rateLimiter,
        private readonly FraudDetectionService $fraudDetectionService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function register(Request $request)
    {
        $passwordRegex = (string) config('security.auth.strong_password_regex', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^\\w\\s]).{10,}$/');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:10', 'regex:' . $passwordRegex, 'confirmed'],
            'referral_code' => ['nullable', 'string', 'max:32'],
        ]);

        $email = strtolower(trim((string) $validated['email']));

        if (User::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Account already exists. Please login.',
                'code' => 'ACCOUNT_EXISTS',
            ], 409);
        }

        try {
            $user = DB::transaction(function () use ($validated, $email, $request): User {
                $user = User::create([
                    'name' => trim((string) $validated['name']),
                    'email' => $email,
                    'password' => Hash::make($validated['password']),
                    'unique_user_id' => $this->generateUniqueUserId(),
                ]);

                $this->referralService->ensureReferralCode($user);

                if (!empty($validated['referral_code'])) {
                    $this->referralService->bindReferral($user, (string) $validated['referral_code'], [
                        'ip_address' => $request->ip(),
                        'user_agent' => (string) $request->userAgent(),
                    ]);
                }

                return $user->fresh();
            });

            $this->userInitializationService->initializeUser($user);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage()
            ], 422);
        }

        $this->logAudit($user->id, 'auth_registered', $request, [
            'referral_code_used' => $validated['referral_code'] ?? null,
        ]);

        \App\Services\AuditService::log($user->id, 'auth', 'register', [
            'referral_code_used' => $validated['referral_code'] ?? null,
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'token' => $token,
            'user' => $user->fresh(),
        ], 201);
    }

    public function checkAccount(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $exists = User::where('email', strtolower(trim((string) $validated['email'])))->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'exists' => true,
                'message' => 'Account already exists. Please login.',
                'code' => 'ACCOUNT_EXISTS',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'exists' => false,
            'message' => 'Account details accepted. Continue onboarding.',
        ]);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_fingerprint' => ['nullable', 'string', 'max:2048'],
        ]);

        $identifier = strtolower(trim((string) $validated['email']));
        $ip = (string) $request->ip();
        $keyByIp = 'security:login:ip:' . $ip;
        $keyByUser = 'security:login:user:' . hash('sha256', $identifier);
        $maxAttempts = (int) config('security.auth.max_login_attempts', 5);
        $decaySeconds = (int) config('security.auth.login_decay_seconds', 60);

        if ($this->rateLimiter->tooManyAttempts($keyByIp, $maxAttempts, $decaySeconds)
            || $this->rateLimiter->tooManyAttempts($keyByUser, $maxAttempts, $decaySeconds)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many login attempts. Please retry shortly.',
                'retry_in_seconds' => max($this->rateLimiter->availableIn($keyByIp), $this->rateLimiter->availableIn($keyByUser)),
            ], 429);
        }

        $user = User::where('email', $identifier)->first();

        if (!$user) {
            $this->rateLimiter->hit($keyByIp, $decaySeconds);
            $this->rateLimiter->hit($keyByUser, $decaySeconds);
            return response()->json([
                'success' => false,
                'message' => 'Account does not exist. Please create an account.',
                'code' => 'ACCOUNT_NOT_FOUND',
            ], 404);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            $this->rateLimiter->hit($keyByIp, $decaySeconds);
            $this->rateLimiter->hit($keyByUser, $decaySeconds);
            $this->logAudit($user->id, 'auth_login_failed', $request, [
                'email' => $identifier,
            ]);

            \App\Services\AuditService::logFailed($user->id, 'auth', 'login_failed', [
                'email' => $identifier,
            ]);

            $this->fraudDetectionService->recordFailedLogin($user, $ip, (string) $request->userAgent());
            event('user.failed_login', [
                'user_id' => $user->id,
                'ip' => $ip,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        if (!Auth::attempt(['email' => $identifier, 'password' => $validated['password']])) {
            $this->rateLimiter->hit($keyByIp, $decaySeconds);
            $this->rateLimiter->hit($keyByUser, $decaySeconds);
            $this->logAudit($user->id, 'auth_login_failed', $request, [
                'email' => $identifier,
            ]);

            \App\Services\AuditService::logFailed($user->id, 'auth', 'login_failed', [
                'email' => $identifier,
            ]);

            $this->fraudDetectionService->recordFailedLogin($user, $ip, (string) $request->userAgent());
            event('user.failed_login', [
                'user_id' => $user->id,
                'ip' => $ip,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        $this->rateLimiter->clear($keyByIp);
        $this->rateLimiter->clear($keyByUser);

        $risk = $this->fraudDetectionService->analyzeLogin(
            $user,
            $ip,
            (string) $request->userAgent(),
            $validated['device_fingerprint'] ?? null,
        );

        if (($risk['risk_level'] ?? 'LOW') === 'HIGH') {
            Auth::logout();

            $this->auditLogService->log($user->id, 'auth_login_blocked_security', $request, [
                'risk' => $risk,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Login blocked. Please contact support.',
                'risk' => $risk,
            ], 403);
        }

        $this->recordLoginDevice(
            $user,
            $request,
            (string) ($validated['device_name'] ?? 'web'),
            $validated['device_fingerprint'] ?? null,
        );

        $request->session()->regenerate();
        $this->logAudit($user->id, 'auth_login_success', $request);

        \App\Services\AuditService::log($user->id, 'auth', 'login');

        event('user.login', [
            'user_id' => $user->id,
            'ip' => $ip,
            'risk_level' => $risk['risk_level'] ?? 'LOW',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $user->createToken('auth_token')->plainTextToken,
            'user' => $user->fresh(),
            'risk' => $risk,
        ]);
    }

    private function generateUniqueUserId(): string
    {
        do {
            $id = 'EXA-' . strtoupper(Str::random(10));
        } while (User::where('unique_user_id', $id)->exists());

        return $id;
    }

    /*
    public function oldRegister(Request $request)
    {
        $passwordRegex = (string) config('security.auth.strong_password_regex', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^\\w\\s]).{10,}$/');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'regex:' . $passwordRegex, 'confirmed'],
            'referral_code' => ['nullable', 'string', 'max:32'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'unique_user_id' => Str::uuid()->toString(),
        ]);

        $this->referralService->ensureReferralCode($user);

        if (!empty($validated['referral_code'])) {
            try {
                $this->referralService->bindReferral($user, (string) $validated['referral_code'], [
                    'ip_address' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ]);
            } catch (RuntimeException $exception) {
                $user->delete();

                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage()
                ], 422);
            }
        }

        // User Initialization Engine
        $this->userInitializationService->initializeUser($user);

        $this->logAudit($user->id, 'auth_registered', $request, [
            'referral_code_used' => $validated['referral_code'] ?? null,
        ]);

        \App\Services\AuditService::log($user->id, 'auth', 'register', [
            'referral_code_used' => $validated['referral_code'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully. Please login.',
        ]);
    }

    public function oldLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_fingerprint' => ['nullable', 'string', 'max:2048'],
        ]);

        $identifier = strtolower((string) $validated['email']);
        $ip = (string) $request->ip();
        $keyByIp = 'security:login:ip:' . $ip;
        $keyByUser = 'security:login:user:' . hash('sha256', $identifier);
        $maxAttempts = (int) config('security.auth.max_login_attempts', 5);
        $decaySeconds = (int) config('security.auth.login_decay_seconds', 60);

        if ($this->rateLimiter->tooManyAttempts($keyByIp, $maxAttempts, $decaySeconds)
            || $this->rateLimiter->tooManyAttempts($keyByUser, $maxAttempts, $decaySeconds)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many login attempts. Please retry shortly.',
                'retry_in_seconds' => max($this->rateLimiter->availableIn($keyByIp), $this->rateLimiter->availableIn($keyByUser)),
            ], 429);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            $this->rateLimiter->hit($keyByIp, $decaySeconds);
            $this->rateLimiter->hit($keyByUser, $decaySeconds);
            return response()->json([
                'success' => false,
                'message' => 'Account does not exist. Please create an account.',
            ], 401);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            $this->rateLimiter->hit($keyByIp, $decaySeconds);
            $this->rateLimiter->hit($keyByUser, $decaySeconds);
            $this->logAudit($user->id, 'auth_login_failed', $request, [
                'email' => $validated['email'],
            ]);

            \App\Services\AuditService::logFailed($user->id, 'auth', 'login_failed', [
                'email' => $validated['email'],
            ]);

            $this->fraudDetectionService->recordFailedLogin($user, $ip, (string) $request->userAgent());
            event('user.failed_login', [
                'user_id' => $user->id,
                'ip' => $ip,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (!Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            $this->rateLimiter->hit($keyByIp, $decaySeconds);
            $this->rateLimiter->hit($keyByUser, $decaySeconds);
            $this->logAudit($user->id, 'auth_login_failed', $request, [
                'email' => $validated['email'],
            ]);

            \App\Services\AuditService::logFailed($user->id, 'auth', 'login_failed', [
                'email' => $validated['email'],
            ]);

            $this->fraudDetectionService->recordFailedLogin($user, $ip, (string) $request->userAgent());
            event('user.failed_login', [
                'user_id' => $user->id,
                'ip' => $ip,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $this->rateLimiter->clear($keyByIp);
        $this->rateLimiter->clear($keyByUser);

        $risk = $this->fraudDetectionService->analyzeLogin(
            $user,
            $ip,
            (string) $request->userAgent(),
            $validated['device_fingerprint'] ?? null,
        );

        if (($risk['risk_level'] ?? 'LOW') === 'HIGH') {
            Auth::logout();

            $this->auditLogService->log($user->id, 'auth_login_blocked_security', $request, [
                'risk' => $risk,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Login blocked. Please contact support.',
                'risk' => $risk,
            ], 403);
        }

        $this->recordLoginDevice(
            $user,
            $request,
            (string) ($validated['device_name'] ?? 'web'),
            $validated['device_fingerprint'] ?? null,
        );

        $request->session()->regenerate();
        $this->logAudit($user->id, 'auth_login_success', $request);

        \App\Services\AuditService::log($user->id, 'auth', 'login');

        event('user.login', [
            'user_id' => $user->id,
            'ip' => $ip,
            'risk_level' => $risk['risk_level'] ?? 'LOW',
        ]);

        return response()->json([
            'success' => true,
            'user' => $user,
            'risk' => $risk,
        ]);
    }
    */

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        $userId = $request->user()?->id;

        if ($request->user()?->currentAccessToken()) {
            $request->user()?->currentAccessToken()?->delete();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->logAudit($userId, 'auth_logout', $request);

        \App\Services\AuditService::log($userId, 'auth', 'logout');

        return response()->json([
            'success' => true,
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Log password reset request (whether user exists or not, for security)
        $this->logAudit($user?->id, 'security_password_reset_requested', $request, [
            'email' => $validated['email'],
        ]);

        \App\Services\AuditService::log($user?->id, 'security', 'password_reset_requested', [
            'email' => $validated['email'],
        ]);

        $status = Password::sendResetLink($validated);

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['status' => 'ok']);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) use ($request) {
                $oldPassword = $user->password;
                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                $user->save();

                // Log successful password change
                $this->logAudit($user->id, 'security_password_changed', $request, [
                    'email' => $user->email,
                ]);

                \App\Services\AuditService::log($user->id, 'security', 'password_changed', [
                    'email' => $user->email,
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Log failed password reset
            $user = User::where('email', $validated['email'])->first();
            $this->logAudit($user?->id, 'security_password_reset_failed', $request, [
                'email' => $validated['email'],
                'reason' => $status,
            ]);

            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['status' => 'ok']);
    }

    public function verifyTwoFactor()
    {
        return response()->json([
            'success' => false,
            'message' => 'Two-factor verification requires dedicated OTP/TOTP provider integration.',
        ], 501);
    }

    /**
     * Change user email address
     * POST /api/profile/email/change
     */
    public function changeEmail(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string'],
        ]);

        // Verify password
        if (!Hash::check($validated['password'], $user->password)) {
            $this->logAudit($user->id, 'security_email_change_failed', $request, [
                'reason' => 'invalid_password',
                'old_email' => $user->email,
                'new_email' => $validated['email'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid password',
            ], 401);
        }

        $oldEmail = $user->email;
        $user->email = $validated['email'];
        $user->save();

        // Log email change
        $this->logAudit($user->id, 'security_email_changed', $request, [
            'old_email' => $oldEmail,
            'new_email' => $validated['email'],
        ]);

        \App\Services\AuditService::log($user->id, 'security', 'email_changed', [
            'old_email' => $oldEmail,
            'new_email' => $validated['email'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email updated successfully',
            'user' => $user,
        ]);
    }

    /**
     * Enable two-factor authentication
     * POST /api/profile/2fa/enable
     */
    public function enable2FA(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        // Log 2FA enable attempt
        $this->logAudit($user->id, 'security_2fa_enable_attempted', $request);

        // Placeholder - real 2FA setup would use a TOTP provider
        $user->two_factor_enabled = true;
        $user->save();

        $this->logAudit($user->id, 'security_2fa_enabled', $request);

        \App\Services\AuditService::log($user->id, 'security', '2fa_enabled');

        return response()->json([
            'success' => true,
            'message' => '2FA enabled successfully',
        ]);
    }

    /**
     * Disable two-factor authentication
     * POST /api/profile/2fa/disable
     */
    public function disable2FA(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        // Verify password
        if (!Hash::check($validated['password'], $user->password)) {
            $this->logAudit($user->id, 'security_2fa_disable_failed', $request, [
                'reason' => 'invalid_password',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid password',
            ], 401);
        }

        $user->two_factor_enabled = false;
        $user->save();

        $this->logAudit($user->id, 'security_2fa_disabled', $request);

        \App\Services\AuditService::log($user->id, 'security', '2fa_disabled');

        return response()->json([
            'success' => true,
            'message' => '2FA disabled successfully',
        ]);
    }

    private function recordLoginDevice(User $user, Request $request, string $deviceName, ?string $deviceFingerprint = null): void
    {
        LoginDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ],
            [
                'device_name' => $deviceName,
                'fingerprint_hash' => $this->fingerprintHash($deviceFingerprint),
                'last_login_at' => now(),
            ]
        );
    }

    private function logAudit(?int $userId, string $action, Request $request, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $request->ip(),
            'device' => (string) $request->userAgent(),
            'metadata' => array_merge($metadata, [
                'user_agent' => (string) $request->userAgent(),
            ]),
        ]);
    }

    private function fingerprintHash(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        return hash('sha256', trim($value));
    }
}
