<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

class RecentAuthenticationService
{
    public function mark(Request $request, Authenticatable $user, int $windowSeconds): bool
    {
        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        $tokenId = $token instanceof PersonalAccessToken ? $token->getKey() : null;
        if (is_numeric($tokenId)) {
            Cache::put($this->cacheKey($user, (int) $tokenId), time(), $windowSeconds);
            return true;
        }

        if ($request->hasSession()) {
            $request->session()->put('auth_recent_at', time());
            return true;
        }

        return false;
    }

    public function isRecent(Request $request, Authenticatable $user, int $windowSeconds): bool
    {
        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        $tokenId = $token instanceof PersonalAccessToken ? $token->getKey() : null;
        $authenticatedAt = is_numeric($tokenId)
            ? (int) Cache::get($this->cacheKey($user, (int) $tokenId), 0)
            : ($request->hasSession() ? (int) $request->session()->get('auth_recent_at', 0) : 0);

        return $authenticatedAt > 0 && (time() - $authenticatedAt) <= $windowSeconds;
    }

    private function cacheKey(Authenticatable $user, int $tokenId): string
    {
        return 'security.recent-auth.'.hash('sha256', $user->getAuthIdentifier().':'.$tokenId);
    }
}
