<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\AdminSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminSecurityLayer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user instanceof Admin && (string) ($user->role ?? '') !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $whitelist = (array) config('security.admin.ip_whitelist', []);
        if ($whitelist !== [] && !in_array((string) $request->ip(), $whitelist, true)) {
            return response()->json(['message' => 'Admin access blocked from this IP.'], 403);
        }

        if ((bool) config('security.admin.require_2fa', true) && !(bool) ($user->two_factor_enabled ?? false)) {
            return response()->json(['message' => 'Admin 2FA is required.'], 403);
        }

        if ($user instanceof Admin) {
            AdminSession::query()
                ->where('admin_id', $user->id)
                ->where('token_id', optional($user->currentAccessToken())->id)
                ->latest()
                ->limit(1)
                ->update(['last_seen_at' => now()]);
        }

        return $next($request);
    }
}

