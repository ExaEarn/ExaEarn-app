<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Temporary developer auth bypass for local development only.
 *
 * WARNING: Do not enable outside local/development environments.
 */
class DevAuthBypass
{
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        $shouldBypass = app()->environment('local', 'development')
            && filter_var(env('DEV_AUTH_BYPASS', false), FILTER_VALIDATE_BOOL);

        if ($shouldBypass) {
            $userId = (int) env('DEV_SUPER_ADMIN_ID', 1);
            $user = User::find($userId);

            if (! $user) {
                return response()->json([
                    'message' => 'Dev auth bypass enabled, but user id '.$userId.' was not found.',
                ], 401);
            }

            Auth::login($user);
            $request->setUserResolver(fn () => $user);

            return $next($request);
        }

        /** @var \Illuminate\Auth\Middleware\Authenticate $auth */
        $auth = app(Authenticate::class);
        $guards = $guards ?: ['sanctum'];

        return $auth->handle($request, $next, ...$guards);
    }
}
