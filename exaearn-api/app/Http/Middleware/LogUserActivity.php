<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = Auth::user();
        if ($user) {
            // Log API calls or specific actions
            $action = $this->getActionFromRequest($request);
            if ($action) {
                AuditService::log($user->id, 'api', $action, [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);
            }
        }

        return $response;
    }

    private function getActionFromRequest(Request $request)
    {
        $path = $request->path();
        $method = $request->method();

        // Define actions based on routes
        if (str_contains($path, 'login')) {
            return 'login_attempt';
        }
        if (str_contains($path, 'logout')) {
            return 'logout';
        }
        if (str_contains($path, 'withdraw')) {
            return 'withdrawal_request';
        }
        if (str_contains($path, 'deposit')) {
            return 'deposit';
        }
        if (str_contains($path, 'trade')) {
            return 'trade_action';
        }
        if (str_contains($path, 'reward')) {
            return 'reward_claim';
        }
        if (str_contains($path, 'staking')) {
            return 'staking_action';
        }
        if (str_contains($path, 'nft')) {
            return 'nft_action';
        }
        if (str_contains($path, 'password')) {
            return 'password_change';
        }
        if (str_contains($path, 'email')) {
            return 'email_change';
        }

        // For general API calls, perhaps don't log all, or log as 'api_call'
        return null; // Don't log every API call to avoid spam
    }
}