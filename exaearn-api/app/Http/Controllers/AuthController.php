<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthService;
use App\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService)
    {
    }

    protected function shouldUseDevAuthBypass(): bool
    {
        return app()->environment('local', 'development') && filter_var(env('DEV_AUTH_BYPASS', false), FILTER_VALIDATE_BOOL);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'referral_code' => ['nullable', 'string', 'max:32'],
        ]);

        $result = $this->authService->register($data);

        AuditService::log($result['user']->id, 'auth', 'register', [
            'email' => $data['email'],
            'referral_code' => $data['referral_code'] ?? null,
        ]);

        return $this->success('Registration successful', [
            'token' => $result['token'],
            'user' => $result['user'],
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        if ($this->shouldUseDevAuthBypass()) {
            $userId = (int) env('DEV_SUPER_ADMIN_ID', 1);
            $user = User::find($userId);

            if (! $user) {
                return $this->error('Dev auth bypass is enabled, but the configured user was not found', 401);
            }

            return $this->success('Login successful (dev bypass)', [
                'token' => 'dev-token-'.$userId,
                'user' => $user,
                'dev_bypass' => true,
            ]);
        }

        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $this->authService->attemptLogin($data['email'], $data['password']);

        if (! $user) {
            AuditService::logFailed(null, 'auth', 'login_failed', [
                'email' => $data['email'],
            ]);
            return $this->error('Invalid credentials', 401);
        }

        if (config('auth.require_email_verification', false) && ! $user->hasVerifiedEmail()) {
            return $this->error('Email address is not verified', 403);
        }

        if ($user->two_factor_enabled) {
            $pendingToken = $this->authService->createPendingTwoFactorToken($user);

            return $this->success('Two-factor authentication required', [
                '2fa_required' => true,
                'user_id' => $user->id,
                'two_factor_token' => $pendingToken,
            ]);
        }

        $this->authService->recordLoginDevice($user, $request);
        $token = $this->authService->issueLoginToken($user);

        AuditService::log($user->id, 'auth', 'login', [
            'device_name' => $data['device_name'] ?? null,
        ]);

        return $this->success('Login successful', [
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $id = $request->input('id', $request->route('id'));
        $hash = $request->input('hash', $request->route('hash'));

        if (! $id || ! $hash) {
            return $this->error('Invalid verification payload', 422);
        }

        // If a signature is present, enforce it. (GET route uses `signed` middleware.)
        if ($request->query('signature') && ! $request->hasValidSignature()) {
            return $this->error('Invalid or expired verification link', 403);
        }

        $user = User::find($id);

        if (! $user) {
            return $this->error('Invalid verification user', 404);
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->error('Invalid verification hash', 400);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->success('Email already verified');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return $this->success('Email verified successfully');
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthorized', 401);
        }

        $setup = $this->authService->generateTwoFactorSetup($user);

        return $this->success('2FA setup generated', $setup);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'otp_code' => ['required', 'string'],
            'two_factor_token' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user() ?? User::find($data['user_id'] ?? 0);

        if (! $user) {
            return $this->error('User not found', 404);
        }

        if ($request->user()) {
            if (! $this->authService->confirmTwoFactor($user, $data['otp_code'])) {
                return $this->error('Invalid OTP code', 400);
            }

            AuditService::log($user->id, 'security', '2fa_enabled');

            return $this->success('2FA enabled successfully');
        }

        // For login flow (unauthenticated), require the pending 2FA token.
        if (! ($data['two_factor_token'] ?? null)) {
            return $this->error('two_factor_token is required', 422);
        }

        if (! $this->authService->isPendingTwoFactorTokenValid($user->id, $data['two_factor_token'])) {
            return $this->error('Invalid or expired 2FA token', 401);
        }

        if (! $this->authService->verifyTwoFactorLogin($user, $data['otp_code'])) {
            return $this->error('Invalid OTP code', 400);
        }

        // Consume the pending token only after the OTP is successfully verified.
        $this->authService->consumePendingTwoFactorToken($user->id, $data['two_factor_token']);

        $this->authService->recordLoginDevice($user, $request);
        $token = $this->authService->issueLoginToken($user);

        AuditService::log($user->id, 'auth', 'login', [
            'device_name' => $data['device_name'] ?? null,
            '2fa' => true,
        ]);

        return $this->success('Login successful', [
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($data);

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->error('Unable to send reset link', 400);
        }

        return $this->success('Password reset email sent');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->password = $password;
            $user->setRememberToken(Str::random(60));
            $user->save();

            AuditService::log($user->id, 'security', 'password_reset');

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error('Invalid reset token', 400);
        }

        return $this->success('Password reset successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthorized', 401);
        }

        $request->user()->currentAccessToken()?->delete();

        AuditService::log($user->id, 'auth', 'logout');

        Log::info('User logged out', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return $this->success('Logout successful');
    }

    protected function success(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge([
            'status' => 'success',
            'message' => $message,
        ], $data));
    }

    protected function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $status);
    }
}
