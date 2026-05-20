<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TreasuryWallet;
use App\Services\Treasury\DepositWatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Treasury Monitoring & Configuration Controller
 *
 * Handles treasury monitoring, wallet watching setup, and status checks.
 */
class TreasuryMonitoringController extends Controller
{
    public function __construct(private readonly DepositWatcher $depositWatcher)
    {
    }

    /**
     * GET /api/admin/treasury/monitoring/status
     * Get treasury monitoring status.
     */
    public function monitoringStatus(): JsonResponse
    {
        $status = [];

        foreach (['ethereum', 'bitcoin', 'polygon'] as $chain) {
            try {
                $watcherStatus = $this->depositWatcher->getWatcherStatus($chain);
                $status[$chain] = [
                    'chain' => $chain,
                    'active_watchers' => count($watcherStatus),
                    'watchers' => $watcherStatus,
                ];
            } catch (\Exception $e) {
                $status[$chain] = [
                    'chain' => $chain,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'data' => $status,
        ]);
    }

    /**
     * POST /api/admin/treasury/monitoring/watch
     * Start watching a treasury wallet for deposits.
     */
    public function startWatching(Request $request): JsonResponse
    {
        $request->validate([
            'wallet_id' => 'required|integer|exists:treasury_wallets,id',
        ]);

        $wallet = TreasuryWallet::findOrFail($request->input('wallet_id'));

        if ($wallet->status !== 'active') {
            return response()->json(['message' => 'Only active wallets can be watched.'], 422);
        }

        try {
            $result = $this->depositWatcher->watchAddress($wallet->address, $wallet->chain);

            if ($result) {
                Log::info('Deposit watcher started for treasury wallet', [
                    'wallet_id' => $wallet->id,
                    'chain' => $wallet->chain,
                    'address' => $wallet->address,
                    'admin_id' => $request->user()->id,
                ]);

                return response()->json([
                    'message' => 'Deposit watching started.',
                    'data' => $wallet,
                ]);
            }

            return response()->json(['message' => 'Failed to start deposit watching.'], 503);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/treasury/monitoring/unwatch
     * Stop watching a treasury wallet.
     */
    public function stopWatching(Request $request): JsonResponse
    {
        $request->validate([
            'wallet_id' => 'required|integer|exists:treasury_wallets,id',
        ]);

        $wallet = TreasuryWallet::findOrFail($request->input('wallet_id'));

        try {
            $result = $this->depositWatcher->stopWatching($wallet->address, $wallet->chain);

            if ($result) {
                Log::info('Deposit watcher stopped for treasury wallet', [
                    'wallet_id' => $wallet->id,
                    'chain' => $wallet->chain,
                    'address' => $wallet->address,
                    'admin_id' => $request->user()->id,
                ]);

                return response()->json([
                    'message' => 'Deposit watching stopped.',
                    'data' => $wallet,
                ]);
            }

            return response()->json(['message' => 'Failed to stop deposit watching.'], 503);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/admin/treasury/monitoring/health
     * Get treasury health metrics.
     */
    public function healthCheck(): JsonResponse
    {
        $hotWallets = TreasuryWallet::where('type', 'hot')->get();
        $coldWallets = TreasuryWallet::where('type', 'cold')->get();

        $health = [
            'hot_wallets' => [
                'count' => $hotWallets->count(),
                'active' => $hotWallets->where('status', 'active')->count(),
                'wallets' => $hotWallets->map(function ($wallet) {
                    return [
                        'id' => $wallet->id,
                        'chain' => $wallet->chain,
                        'address' => $wallet->address,
                        'status' => $wallet->status,
                        'label' => $wallet->label,
                    ];
                }),
            ],
            'cold_wallets' => [
                'count' => $coldWallets->count(),
                'active' => $coldWallets->where('status', 'active')->count(),
                'wallets' => $coldWallets->map(function ($wallet) {
                    return [
                        'id' => $wallet->id,
                        'chain' => $wallet->chain,
                        'address' => $wallet->address,
                        'status' => $wallet->status,
                        'label' => $wallet->label,
                    ];
                }),
            ],
        ];

        return response()->json([
            'data' => $health,
        ]);
    }
}