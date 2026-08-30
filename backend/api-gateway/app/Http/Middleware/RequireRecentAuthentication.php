<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRecentAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $authenticatedAt = (int) $request->session()->get('auth_recent_at', 0);
        $window = (int) config('security.auth.recent_auth_seconds', 900);

        if ($authenticatedAt <= 0 || (time() - $authenticatedAt) > $window) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RECENT_AUTH_REQUIRED',
                    'message' => 'Confirm your identity before continuing.',
                    'methods' => $request->user()?->two_factor_enabled ? ['password', 'totp'] : ['password'],
                ],
            ], 428);
        }

        return $next($request);
    }
}
