<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
        if (app()->environment('local', 'development', 'testing')) {
            if (Auth::check()) {
                $user = Auth::user();
                $request->setUserResolver(fn () => $user);

                return $next($request);
            }

            $configuredUserId = (int) env('DEV_SUPER_ADMIN_ID', 0);
            $user = $configuredUserId > 0 ? User::find($configuredUserId) : null;

            if (!$user) {
                $user = User::query()->orderBy('id')->first();
            }

            if (!$user) {
                $user = User::query()->create([
                    'name' => env('DEV_AUTH_USER_NAME', 'ExaEarn Dev User'),
                    'email' => env('DEV_AUTH_USER_EMAIL', 'dev@exaearn.local'),
                    'password' => Hash::make(Str::random(32)),
                    'unique_user_id' => 'DEV-' . strtoupper(Str::random(10)),
                    'role' => env('DEV_AUTH_USER_ROLE', 'user'),
                    'email_verified_at' => now(),
                ]);
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
