<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TreasuryTransaction;
use App\Models\TreasuryWallet;
use App\Models\WithdrawRequest;
use App\Services\Treasury\TreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TreasuryController extends Controller
{
    public function __construct(private readonly TreasuryService $treasuryService)
    {
    }

    // ─── Treasury Wallets ──────────────────────────────────────────

    /**
     * GET /api/admin/treasury/wallets
     * List all treasury wallets.
     */
    public function wallets(): JsonResponse
    {
        $wallets = TreasuryWallet::all();

        return response()->json([
            'data' => $wallets,
        ]);
    }

    /**
     * POST /api/admin/treasury/wallets
     * Create a new treasury wallet.
     */
    public function createWallet(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'type' => ['required', 'string', 'in:hot,cold,system'],
            'chain' => ['required', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:128'],
            'label' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);

        $wallet = TreasuryWallet::create($payload);

        Log::info('Treasury wallet created', [
            'wallet_id' => $wallet->id,
            'type' => $wallet->type,
            'chain' => $wallet->chain,
            'admin_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Treasury wallet created.',
            'data' => $wallet,
        ], 201);
    }

    // ─── Treasury Balances ─────────────────────────────────────────

    /**
     * GET /api/admin/treasury/balances
     * Get treasury balances for all chains/assets.
     */
    public function balances(): JsonResponse
    {
        $balances = [];
        $chains = ['ethereum', 'bitcoin', 'polygon']; // Example chains

        foreach ($chains as $chain) {
            $balances[$chain] = [
                'hot' => $this->treasuryService->getHotBalance($chain, 'USDT'),
                'cold' => $this->treasuryService->getColdBalance($chain, 'USDT'),
            ];
        }

        return response()->json([
            'data' => $balances,
        ]);
    }

    // ─── Treasury Moves ────────────────────────────────────────────

    /**
     * POST /api/admin/treasury/move-to-cold
     * Move funds from hot to cold wallet.
     */
    public function moveToCold(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'chain' => ['required', 'string', 'max:32'],
            'asset' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $transaction = $this->treasuryService->moveToCold(
                $payload['chain'],
                $payload['asset'],
                (string) $payload['amount'],
                $request->user()->id
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Funds moved to cold wallet.',
            'data' => $transaction,
        ]);
    }

    /**
     * POST /api/admin/treasury/move-to-hot
     * Move funds from cold to hot wallet.
     */
    public function moveToHot(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'chain' => ['required', 'string', 'max:32'],
            'asset' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $transaction = $this->treasuryService->moveToHot(
                $payload['chain'],
                $payload['asset'],
                (string) $payload['amount'],
                $request->user()->id
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Funds moved to hot wallet.',
            'data' => $transaction,
        ]);
    }

    // ─── Withdrawal Management ─────────────────────────────────────

    /**
     * GET /api/admin/treasury/withdraw-requests
     * List withdrawal requests.
     */
    public function withdrawRequests(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');
        $requests = WithdrawRequest::with('user')
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $requests,
        ]);
    }

    /**
     * POST /api/admin/treasury/withdraw-requests/{id}/approve
     * Approve a withdrawal request.
     */
    public function approveWithdraw(Request $request, int $id): JsonResponse
    {
        try {
            $withdrawRequest = $this->treasuryService->approveWithdraw($id, $request->user()->id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Withdrawal request approved.',
            'data' => $withdrawRequest,
        ]);
    }

    /**
     * POST /api/admin/treasury/withdraw-requests/{id}/sign
     * Sign and send a withdrawal.
     */
    public function signWithdraw(Request $request, int $id): JsonResponse
    {
        try {
            $withdrawRequest = $this->treasuryService->signWithdraw($id, $request->user()->id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Withdrawal signed and queued for sending.',
            'data' => $withdrawRequest,
        ]);
    }

    // ─── Treasury Transactions ─────────────────────────────────────

    /**
     * GET /api/admin/treasury/transactions
     * List treasury transactions.
     */
    public function transactions(Request $request): JsonResponse
    {
        $query = TreasuryTransaction::query();

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->has('chain')) {
            $query->where('chain', $request->query('chain'));
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json([
            'data' => $transactions,
        ]);
    }
}