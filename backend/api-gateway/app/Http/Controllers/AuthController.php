<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LoginDevice;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DashboardExperienceRegistry;
use App\Services\FraudDetectionService;
use App\Services\RateLimiterService;
use App\Services\ReferralService;
use App\Services\ProfileIdentityService;
use App\Services\ExperienceRecommendationService;
use App\Services\UserInitializationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;
use App\Http\Middleware\Verify2FA;
use App\Notifications\DeveloperPasswordResetNotification;
use App\Services\SessionSecurityService;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService,
        private readonly UserInitializationService $userInitializationService,
        private readonly RateLimiterService $rateLimiter,
        private readonly FraudDetectionService $fraudDetectionService,
        private readonly AuditLogService $auditLogService,
        private readonly ProfileIdentityService $profileIdentityService,
        private readonly ExperienceRecommendationService $experienceRecommendationService,
        private readonly SessionSecurityService $sessionSecurityService,
    ) {
    }

    private function userPayload(User $user): array
    {
        return array_merge($user->toArray(), [
            'profile_identity' => $this->profileIdentityService->identityFor($user, 'self'),
            'verification' => [
                'kyc_verified' => (bool) $user->kyc_verified_at,
                'kyc_level' => (int) ($user->kyc_level ?? 0),
                'verified_at' => optional($user->kyc_verified_at)->toIso8601String(),
            ],
        ]);
    }
    public function register(Request $request)
    {
        $passwordRegex = (string) config('security.auth.strong_password_regex', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^\\w\\s]).{10,}$/');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:10', 'regex:' . $passwordRegex, 'confirmed'],
            'referral_code' => ['nullable', 'string', 'max:32'],
            'dashboard_preferences' => ['nullable', 'array'],
            'dashboard_preferences.mode' => ['required_with:dashboard_preferences', 'in:all,personalized'],
            'dashboard_preferences.primary_interest' => ['nullable', 'in:' . implode(',', DashboardExperienceRegistry::KEYS)],
            'dashboard_preferences.selected_interests' => ['array', 'max:6'],
            'dashboard_preferences.selected_interests.*' => ['distinct', 'in:' . implode(',', DashboardExperienceRegistry::KEYS)],
            'dashboard_preferences.experience_level' => ['nullable', 'in:' . implode(',', ExperienceRecommendationService::EXPERIENCES)],
            'dashboard_preferences.primary_goal' => ['nullable', 'in:' . implode(',', ExperienceRecommendationService::GOALS)],
            'dashboard_preferences.interests' => ['nullable', 'array', 'max:3'],
            'dashboard_preferences.interests.*' => ['distinct', 'in:' . implode(',', ExperienceRecommendationService::INTERESTS)],
            'dashboard_preferences.selected_mode' => ['nullable', 'in:' . implode(',', ExperienceRecommendationService::MODES)],
            'dashboard_preferences.onboarding_version' => ['nullable', 'integer', 'min:4'],
            'dashboard_preferences.onboarding_completed' => ['nullable', 'boolean'],
            'registration_context' => ['nullable', 'string', 'in:exchange,developers'],
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
            $dashboardPreferences = $this->normalizeOnboardingPreferences((array) ($validated['dashboard_preferences'] ?? []));
            $user = DB::transaction(function () use ($validated, $email, $request, $dashboardPreferences): User {
                $user = User::create([
                    'name' => trim((string) $validated['name']),
                    'email' => $email,
                    'password' => Hash::make($validated['password']),
                    'unique_user_id' => $this->generateUniqueUserId(),
                    'preferences' => $dashboardPreferences !== [] ? ['dashboard' => $dashboardPreferences] : null,
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
        $request->session()->put('auth_recent_at', time());
        $token = $user->createToken('auth_token')->plainTextToken;

        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
            $this->auditLogService->log($user->id, 'auth_email_verification_sent', $request, [
                'registration_context' => $validated['registration_context'] ?? 'exchange',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'token' => $token,
            'user' => $this->userPayload($user->fresh()),
            'next' => ($validated['registration_context'] ?? null) === 'developers'
                ? 'developer_email_verification'
                : 'account_onboarding',
        ], 201);
    }

    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = $request->user();
        abort_unless($user && (int) $user->id === $id, 403);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $this->auditLogService->log($user->id, 'auth_email_verified', $request, ['source' => 'developers']);
        }

        return redirect(rtrim((string) config('app.developer_portal_url'), '/') . '/developers/onboarding?verified=1');
    }

    public function resendEmailVerification(Request $request)
    {
        $user = $request->user();
        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
            $this->auditLogService->log($user->id, 'auth_email_verification_resent', $request);
        }

        return response()->json([
            'success' => true,
            'message' => 'If verification is pending, a new email has been sent.',
        ]);
    }

    private function normalizeOnboardingPreferences(array $preferences): array
    {
        if (! isset($preferences['experience_level'], $preferences['primary_goal'])) return $preferences;
        $interests = array_values($preferences['interests'] ?? $this->experienceRecommendationService->inferInterests((string) $preferences['primary_goal']));
        $recommendation = $this->experienceRecommendationService->recommend((string) $preferences['experience_level'], (string) $preferences['primary_goal'], $interests);
        $legacy = $this->experienceRecommendationService->legacyIntent((string) $preferences['primary_goal']);
        return array_merge($preferences, [
            'mode' => 'personalized', 'primary_interest' => $legacy, 'selected_interests' => [$legacy],
            'interests' => $interests, 'recommended_mode' => $recommendation['recommended_mode'],
            'selected_mode' => $preferences['selected_mode'] ?? $recommendation['recommended_mode'],
            'recommendation_reason_codes' => $recommendation['reason_codes'], 'onboarding_version' => 4,
            'onboarding_completed' => true, 'completed_at' => now()->toISOString(),
        ]);
    }

    public function checkAccount(Request $request)
    {
        $validatedEmail = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim((string) $validatedEmail['email']));
        $exists = User::where('email', $email)->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'exists' => true,
                'message' => 'Account already exists. Please login.',
                'code' => 'ACCOUNT_EXISTS',
            ], 409);
        }

        if ($request->boolean('validate_credentials')) {
            $passwordRegex = (string) config('security.auth.strong_password_regex', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^\\w\\s]).{10,}$/');

            $request->merge(['email' => $email]);
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:10', 'regex:' . $passwordRegex, 'confirmed'],
                'referral_code' => ['nullable', 'string', 'max:32'],
            ]);

            $referralCode = strtoupper(trim((string) $request->input('referral_code', '')));
            if ($referralCode !== '' && !User::where('referral_code', $referralCode)->exists()) {
                return response()->json([
                    'success' => false,
                    'exists' => false,
                    'message' => 'Referral code is invalid.',
                    'code' => 'INVALID_REFERRAL_CODE',
                ], 422);
            }
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
            'two_factor_code' => ['nullable', 'string', 'size:6'],
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
                'message' => 'Incorrect email or password.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
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

        $accountStatus = strtoupper((string) ($user->account_status ?: 'FULLY_ACTIVE'));
        if (!in_array($accountStatus, ['ACTIVE', 'FULLY_ACTIVE'], true)) {
            $this->auditLogService->log($user->id, 'auth_login_blocked_account_status', $request, ['status' => $accountStatus]);
            return response()->json([
                'success' => false,
                'message' => 'This account cannot sign in. Please use account recovery or contact support.',
                'code' => in_array($accountStatus, ['LOCKED', 'SECURITY_LOCKED'], true) ? 'ACCOUNT_LOCKED' : 'ACCOUNT_DISABLED',
            ], 403);
        }

        if ($user->two_factor_enabled) {
            $code = (string) ($validated['two_factor_code'] ?? '');
            if ($code === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Enter the code from your authenticator app.',
                    'code' => '2FA_REQUIRED',
                ], 428);
            }
            if (!$user->two_factor_secret || !Verify2FA::verifyCode((string) $user->two_factor_secret, $code)) {
                $this->rateLimiter->hit($keyByUser, $decaySeconds);
                $this->auditLogService->log($user->id, 'auth_2fa_failed', $request);
                return response()->json([
                    'success' => false,
                    'message' => 'The authentication code is invalid or expired.',
                    'code' => 'INVALID_2FA_CODE',
                ], 422);
            }
            $this->auditLogService->log($user->id, 'auth_2fa_success', $request);
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
            'user' => $this->userPayload($user->fresh()),
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
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $userId = $request->user()?->id;

        $token = $request->user()?->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        $guard = Auth::guard();
        if (method_exists($guard, 'logout')) {
            $guard->logout();
        }
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

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

        $email = strtolower(trim((string) $validated['email']));
        $user = User::where('email', $email)->first();

        // Log password reset request (whether user exists or not, for security)
        $this->logAudit($user?->id, 'security_password_reset_requested', $request, [
            'email_hash' => hash('sha256', $email),
        ]);

        \App\Services\AuditService::log($user?->id, 'security', 'password_reset_requested', [
            'email_hash' => hash('sha256', $email),
        ]);

        if ($user) {
            $token = Password::broker()->createToken($user);
            $user->notify(new DeveloperPasswordResetNotification($token));
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'If an account exists for that email, reset instructions have been sent.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $passwordRegex = (string) config('security.auth.strong_password_regex');
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:10', 'regex:'.$passwordRegex, 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) use ($request) {
                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                $user->save();
                $user->tokens()->delete();
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }

                // Log successful password change
                $this->logAudit($user->id, 'security_password_changed', $request, [
                    'email_hash' => hash('sha256', strtolower((string) $user->email)),
                ]);

                \App\Services\AuditService::log($user->id, 'security', 'password_changed', [
                    'email_hash' => hash('sha256', strtolower((string) $user->email)),
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Log failed password reset
            $user = User::where('email', $validated['email'])->first();
            $this->logAudit($user?->id, 'security_password_reset_failed', $request, ['reason' => 'invalid_or_expired_token']);
            return response()->json(['message' => 'This reset link is invalid or has expired.', 'code' => 'INVALID_RESET_TOKEN'], 422);
        }

        return response()->json(['status' => 'ok']);
    }

    public function reauthenticate(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'two_factor_code' => ['nullable', 'string', 'size:6'],
        ]);

        $valid = Hash::check((string) $validated['password'], (string) $user->password);
        if ($valid && $user->two_factor_enabled) {
            $valid = $user->two_factor_secret
                && Verify2FA::verifyCode((string) $user->two_factor_secret, (string) ($validated['two_factor_code'] ?? ''));
        }
        if (!$valid) {
            $this->rateLimiter->hit('security:reauth:user:'.$user->id, 60);
            $this->auditLogService->log($user->id, 'developer.auth.reauthentication.failed', $request);
            return response()->json(['success' => false, 'message' => 'Security confirmation failed.', 'code' => 'REAUTHENTICATION_FAILED'], 422);
        }

        $request->session()->put('auth_recent_at', time());
        $this->auditLogService->log($user->id, 'developer.auth.reauthentication.success', $request);
        return response()->json(['success' => true, 'recent_auth_expires_in' => (int) config('security.auth.recent_auth_seconds')]);
    }

    public function sessions(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        return response()->json(['success' => true, 'data' => [
            'sessions' => $this->sessionSecurityService->activeSessions($user),
            'devices' => $user->loginDevices()->latest('last_login_at')->limit(20)->get()->map(fn (LoginDevice $device) => [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'browser' => $device->user_agent,
                'approximate_region' => null,
                'last_active' => $device->last_login_at?->toIso8601String(),
            ]),
        ]]);
    }

    public function revokeSession(Request $request, int $tokenId)
    {
        abort_unless($this->sessionSecurityService->revokeSession($request->user(), $tokenId), 404);
        $this->auditLogService->log($request->user()->id, 'developer.auth.session_revoked', $request, ['token_id' => $tokenId]);
        return response()->json(['success' => true]);
    }

    public function logoutAll(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->delete();
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $this->auditLogService->log($user->id, 'developer.auth.logout_all', $request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['success' => true]);
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
