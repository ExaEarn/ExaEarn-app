<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminSession;
use App\Services\AdminAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var Admin|null $admin */
        $admin = Admin::query()->with('role.permissions')->where('email', strtolower((string) $payload['email']))->first();

        if (!$admin || !Hash::check((string) $payload['password'], (string) $admin->password)) {
            $this->audit->log($admin, 'admin.login_failed', ['email' => $payload['email']], $request);
            throw ValidationException::withMessages(['email' => ['Invalid admin credentials.']]);
        }

        if ($admin->status !== 'active') {
            $this->audit->log($admin, 'admin.login_blocked', ['status' => $admin->status], $request);
            return response()->json(['message' => 'Admin account is not active.'], 403);
        }

        $token = $admin->createToken($payload['device_name'] ?? 'admin-api', ['admin'])->plainTextToken;
        $tokenId = explode('|', $token, 2)[0] ?? null;

        AdminSession::query()->create([
            'admin_id' => $admin->id,
            'token_id' => $tokenId,
            'ip' => $request->ip(),
            'device' => $request->userAgent(),
            'last_seen_at' => now(),
        ]);

        $this->audit->log($admin, 'admin.login', ['token_id' => $tokenId], $request);

        return response()->json([
            'token' => $token,
            'admin' => $admin->fresh('role.permissions'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $token = $admin->currentAccessToken();

        if ($token) {
            AdminSession::query()
                ->where('admin_id', $admin->id)
                ->where('token_id', $token->id)
                ->update(['revoked_at' => now()]);
            $token->delete();
        }

        $this->audit->log($admin, 'admin.logout', ['token_id' => $token?->id], $request);

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()?->load('role.permissions')]);
    }
}
