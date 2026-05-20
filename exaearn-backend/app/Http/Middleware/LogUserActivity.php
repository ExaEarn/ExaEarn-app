<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log authenticated API requests
        if ($request->user() && $request->is('api/*')) {
            $this->logActivity($request, $response);
        }

        return $response;
    }

    private function logActivity(Request $request, Response $response): void
    {
        $user = $request->user();
        if (!$user || !isset($user->id)) {
            return;
        }

        $path = $request->getPathInfo();
        $method = $request->getMethod();
        $statusCode = $response->getStatusCode();
        $isSuccess = $statusCode >= 200 && $statusCode < 300;

        // Map routes to activity types
        if (str_contains($path, '/auth/')) {
            $this->logAuthActivity($user->id, $path, $method, $isSuccess);
        } elseif (str_contains($path, '/wallet') || str_contains($path, '/deposit') || str_contains($path, '/withdraw')) {
            $this->logWalletActivity($user->id, $path, $method, $isSuccess, $request);
        } elseif (str_contains($path, '/trade') || str_contains($path, '/order')) {
            $this->logTradeActivity($user->id, $path, $method, $isSuccess, $request);
        } elseif (str_contains($path, '/reward')) {
            $this->logRewardActivity($user->id, $path, $method, $isSuccess, $request);
        } elseif (str_contains($path, '/staking')) {
            $this->logStakingActivity($user->id, $path, $method, $isSuccess, $request);
        } elseif (str_contains($path, '/nft')) {
            $this->logNftActivity($user->id, $path, $method, $isSuccess, $request);
        }
    }

    private function logAuthActivity(int $userId, string $path, string $method, bool $isSuccess): void
    {
        if (str_contains($path, 'logout')) {
            AuditService::log($userId, 'auth', 'logout');
        }
    }

    private function logWalletActivity(int $userId, string $path, string $method, bool $isSuccess, Request $request): void
    {
        $action = 'unknown';
        $data = [];

        if (str_contains($path, 'deposit')) {
            $action = 'deposit_initiated';
            $data = $this->extractWalletData($request);
        } elseif (str_contains($path, 'withdraw')) {
            $action = 'withdrawal_requested';
            $data = $this->extractWalletData($request);
        } elseif (str_contains($path, 'transfer')) {
            $action = 'transfer';
            $data = $this->extractWalletData($request);
        } elseif ($method === 'GET' && str_contains($path, 'wallet')) {
            return; // Skip read operations
        }

        if ($action !== 'unknown') {
            AuditService::log($userId, 'wallet', $action, $data);
        }
    }

    private function logTradeActivity(int $userId, string $path, string $method, bool $isSuccess, Request $request): void
    {
        if ($method === 'GET') {
            return; // Skip read operations
        }

        $action = 'unknown';
        $data = [];

        if (str_contains($path, 'order') && $method === 'POST') {
            $action = 'order_created';
            $data = $this->extractTradeData($request);
        } elseif (str_contains($path, 'order') && $method === 'DELETE') {
            $action = 'order_cancelled';
            $data = ['order_id' => $this->extractId($path)];
        }

        if ($action !== 'unknown') {
            AuditService::log($userId, 'trade', $action, $data);
        }
    }

    private function logRewardActivity(int $userId, string $path, string $method, bool $isSuccess, Request $request): void
    {
        if ($method !== 'POST') {
            return; // Only log writes
        }

        $action = 'reward_claimed';
        $data = [];

        if (str_contains($path, 'checkin')) {
            $action = 'checkin_reward';
        } elseif (str_contains($path, 'mission')) {
            $action = 'mission_reward';
        } elseif (str_contains($path, 'referral')) {
            $action = 'referral_reward';
        }

        AuditService::log($userId, 'reward', $action, $data);
    }

    private function logStakingActivity(int $userId, string $path, string $method, bool $isSuccess, Request $request): void
    {
        if ($method === 'GET') {
            return; // Skip read operations
        }

        $action = 'unknown';
        $data = $this->extractStakingData($request);

        if (str_contains($path, 'stake') && $method === 'POST') {
            $action = 'stake';
        } elseif (str_contains($path, 'unstake')) {
            $action = 'unstake';
        } elseif (str_contains($path, 'claim')) {
            $action = 'claim_reward';
        }

        if ($action !== 'unknown') {
            AuditService::log($userId, 'staking', $action, $data);
        }
    }

    private function logNftActivity(int $userId, string $path, string $method, bool $isSuccess, Request $request): void
    {
        if ($method === 'GET') {
            return; // Skip read operations
        }

        $action = 'unknown';
        $data = ['nft_id' => $this->extractId($path)];

        if (str_contains($path, 'mint')) {
            $action = 'mint';
        } elseif (str_contains($path, 'buy')) {
            $action = 'buy';
        } elseif (str_contains($path, 'sell')) {
            $action = 'sell';
        } elseif (str_contains($path, 'transfer')) {
            $action = 'transfer';
        }

        if ($action !== 'unknown') {
            AuditService::log($userId, 'nft', $action, $data);
        }
    }

    private function extractWalletData(Request $request): array
    {
        return [
            'amount' => $request->input('amount'),
            'asset' => $request->input('asset') ?? $request->input('currency'),
            'address' => $request->input('address'),
        ];
    }

    private function extractTradeData(Request $request): array
    {
        return [
            'pair' => $request->input('pair') ?? $request->input('symbol'),
            'price' => $request->input('price'),
            'amount' => $request->input('amount'),
            'side' => $request->input('side'),
            'order_type' => $request->input('type'),
        ];
    }

    private function extractStakingData(Request $request): array
    {
        return [
            'pool_id' => $request->input('pool_id'),
            'amount' => $request->input('amount'),
            'duration' => $request->input('duration'),
        ];
    }

    private function extractId(string $path): ?string
    {
        $parts = explode('/', trim($path, '/'));
        return is_numeric(end($parts)) ? end($parts) : null;
    }
}
