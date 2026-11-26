<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use App\Services\RecentAuthenticationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRecentAuthentication
{
    public function __construct(private readonly RecentAuthenticationService $recentAuthenticationService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $window = (int) config('security.auth.recent_auth_seconds', 900);

        if (! $user || ! $this->recentAuthenticationService->isRecent($request, $user, $window)) {
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
