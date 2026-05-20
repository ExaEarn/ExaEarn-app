<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use App\Services\DepositService;
use App\Services\TransferService;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly DepositService $depositService,
        private readonly TransferService $transferService,
        private readonly WithdrawalService $withdrawalService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(200, (int) $request->query('per_page', 50)));
        $query = Transaction::query()->latest();

        foreach (['user_id', 'type', 'status', 'currency', 'reference', 'tx_hash'] as $field) {
            if ($request->filled($field)) {
                $value = $field === 'currency'
                    ? strtoupper((string) $request->query($field))
                    : $request->query($field);
                $query->where($field, $value);
            }
        }

        return response()->json([
            'data' => $query->paginate($perPage),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $transaction = $this->transactions->findByTransactionId($id)
            ?? Transaction::query()->findOrFail($id);

        return response()->json([
            'data' => $transaction,
        ]);
    }

    public function userTransactions(Request $request): JsonResponse
    {
        $userId = $request->user()?->id ?? (int) $request->query('user_id');
        if (!$userId) {
            return response()->json([
                'message' => 'user_id is required when not authenticated.',
            ], 422);
        }

        $perPage = max(1, min(200, (int) $request->query('per_page', 50)));
        $query = $this->transactions->listByUser($userId);

        foreach (['type', 'status', 'reference', 'tx_hash'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }

        if ($request->filled('currency')) {
            $query->where('currency', strtoupper((string) $request->query('currency')));
        }

        return response()->json([
            'data' => $query->paginate($perPage),
        ]);
    }

    public function transfer(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'from_user_id' => ['required', 'integer'],
            'to_user_id' => ['required', 'integer', 'different:from_user_id'],
            'currency' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $transaction = $this->transferService->transfer(
                (int) $payload['from_user_id'],
                (int) $payload['to_user_id'],
                (string) $payload['currency'],
                (string) $payload['amount'],
                $payload['reference'] ?? null,
                $payload['metadata'] ?? []
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Transfer completed.',
            'data' => $transaction,
        ], 201);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => ['required', 'integer'],
            'currency' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $transaction = $this->withdrawalService->request(
                (int) $payload['user_id'],
                (string) $payload['currency'],
                (string) $payload['amount'],
                $payload['reference'] ?? null,
                $payload['metadata'] ?? []
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Withdrawal request queued.',
            'data' => $transaction,
        ], 201);
    }

    public function depositWebhook(Request $request): JsonResponse
    {
        $webhookSecret = config('services.node.webhook_secret');
        $incomingSecret = $request->header('X-Webhook-Token');

        if (!$webhookSecret || !$incomingSecret || !hash_equals($webhookSecret, $incomingSecret)) {
            return response()->json([
                'message' => 'Unauthorized webhook request.',
            ], 401);
        }

        $payload = $request->validate([
            'user_id' => ['required', 'integer'],
            'currency' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'tx_hash' => ['nullable', 'string', 'max:150'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $transaction = $this->depositService->processDeposit(
                (int) $payload['user_id'],
                (string) $payload['currency'],
                (string) $payload['amount'],
                $payload['reference'] ?? null,
                $payload['tx_hash'] ?? null,
                $payload['metadata'] ?? []
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Deposit processed.',
            'data' => $transaction,
        ], 201);
    }
}
