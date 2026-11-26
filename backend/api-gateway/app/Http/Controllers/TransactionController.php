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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json([
                'message' => 'Authentication is required to view transaction history.',
            ], 401);
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'max:32'],
            'currency' => ['nullable', 'string', 'max:16'],
            'reference' => ['nullable', 'string', 'max:120'],
            'tx_hash' => ['nullable', 'string', 'max:160'],
            'min_amount' => ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,12}(?:\.\d{1,8})?$/'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,12}(?:\.\d{1,8})?$/'],
            'direction' => ['nullable', 'in:debit,credit'],
            'network' => ['nullable', 'string', 'max:64'],
            'wallet_id' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'cursor' => ['nullable', 'string', 'max:512'],
            'cursor_mode' => ['sometimes', 'boolean'],
        ]);
        if (isset($filters['min_amount'], $filters['max_amount'])) {
            $decimalCompare = static function (string $left, string $right): int {
                [$leftWhole, $leftFraction] = array_pad(explode('.', $left, 2), 2, '');
                [$rightWhole, $rightFraction] = array_pad(explode('.', $right, 2), 2, '');
                $leftWhole = ltrim($leftWhole, '0') ?: '0';
                $rightWhole = ltrim($rightWhole, '0') ?: '0';
                $wholeComparison = strlen($leftWhole) <=> strlen($rightWhole);
                if ($wholeComparison !== 0) return $wholeComparison;
                $wholeComparison = strcmp($leftWhole, $rightWhole);
                return $wholeComparison !== 0
                    ? $wholeComparison
                    : strcmp(str_pad($leftFraction, 8, '0'), str_pad($rightFraction, 8, '0'));
            };
            if ($decimalCompare((string) $filters['max_amount'], (string) $filters['min_amount']) < 0) {
                throw ValidationException::withMessages([
                    'max_amount' => 'The maximum amount must be greater than or equal to the minimum amount.',
                ]);
            }
        }
        if ((isset($filters['min_amount']) || isset($filters['max_amount'])) && empty($filters['currency'])) {
            throw ValidationException::withMessages([
                'currency' => 'Select a currency when filtering by amount.',
            ]);
        }

        $perPage = (int) ($filters['per_page'] ?? 50);
        // The authenticated user ID is the sole account scope. Client-supplied
        // user_id values are intentionally ignored.
        $query = $this->transactions->listByUser((int) $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        foreach (['type', 'status', 'reference', 'tx_hash'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (!empty($filters['currency'])) {
            $query->where('currency', strtoupper($filters['currency']));
        }

        if (!empty($filters['search'])) {
            $needle = '%' . mb_strtolower(trim($filters['search'])) . '%';
            $query->where(function ($searchQuery) use ($needle): void {
                foreach (['reference', 'tx_hash', 'currency', 'type'] as $field) {
                    $searchQuery->orWhereRaw("LOWER(COALESCE({$field}, '')) LIKE ?", [$needle]);
                }
                $driver = DB::connection()->getDriverName();
                foreach (['merchant', 'merchant_name', 'sender', 'sender_name', 'recipient', 'recipient_name', 'counterparty', 'from_address', 'to_address', 'description'] as $field) {
                    if ($driver === 'mysql') {
                        $expression = "LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.{$field}')), ''))";
                        $searchQuery->orWhereRaw("{$expression} LIKE ?", [$needle]);
                    } elseif (in_array($driver, ['pgsql', 'sqlite'], true)) {
                        $expression = "LOWER(COALESCE(metadata->>'{$field}', ''))";
                        $searchQuery->orWhereRaw("{$expression} LIKE ?", [$needle]);
                    } else {
                        $searchQuery->orWhere("metadata->{$field}", 'LIKE', $needle);
                    }
                }
            });
        }

        if (!empty($filters['from'])) {
            $from = Carbon::parse($filters['from']);
            $query->where('created_at', '>=', preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from']) ? $from->startOfDay() : $from);
        }

        if (!empty($filters['to'])) {
            $to = Carbon::parse($filters['to']);
            $query->where('created_at', '<=', preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to']) ? $to->endOfDay() : $to);
        }

        if (isset($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }
        if (isset($filters['max_amount'])) {
            $query->where('amount', '<=', $filters['max_amount']);
        }
        if (!empty($filters['network'])) {
            $query->where(function ($networkQuery) use ($filters): void {
                $networkQuery->where('metadata->network', $filters['network'])
                    ->orWhere('metadata->chain', $filters['network']);
            });
        }
        if (!empty($filters['wallet_id'])) {
            $query->where('metadata->wallet_id', $filters['wallet_id']);
        }
        if (!empty($filters['direction'])) {
            $creditTypes = [
                'deposit', 'receive', 'credit', 'refund', 'staking_reward', 'staking_unlock',
                'nft_sale', 'lottery_reward', 'referral_reward', 'platform_reward', 'sports_payout',
                'agri_payout', 'game_reward', 'edtech_reward', 'giftcard_sell', 'giftcard_refund',
                'p2p_escrow_release', 'p2p_escrow_return',
            ];
            $debitTypes = [
                'withdrawal', 'withdraw', 'send', 'debit', 'transfer', 'internal_transfer',
                'staking_lock', 'nft_purchase', 'lottery_bet', 'subscription_payment',
                'giftcard_buy', 'p2p_escrow_lock',
            ];
            $types = $filters['direction'] === 'credit' ? $creditTypes : $debitTypes;
            $query->whereIn('type', $types);
        }

        $toHistoryItem = static function (Transaction $transaction): array {
            $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
            $item = [
                'transaction_id' => $transaction->transaction_id,
                'type' => $transaction->getRawOriginal('type'),
                'currency' => $transaction->currency,
                'amount' => $transaction->amount,
                'fee' => $transaction->fee,
                'status' => $transaction->getRawOriginal('status'),
                'reference' => $transaction->reference,
                'tx_hash' => $transaction->tx_hash,
                'created_at' => $transaction->created_at,
            ];
            foreach ([
                'merchant', 'merchant_name', 'sender', 'sender_name', 'recipient', 'recipient_name', 'counterparty', 'from_address', 'to_address',
                'destination', 'description', 'network', 'chain', 'wallet_id', 'wallet_uuid',
                'precision', 'decimals', 'settlement_status', 'reconciliation_status',
            ] as $safeKey) {
                if (isset($metadata[$safeKey]) && (is_scalar($metadata[$safeKey]) || $metadata[$safeKey] === null)) {
                    $item[$safeKey] = $metadata[$safeKey];
                }
            }
            return $item;
        };

        if ($request->boolean('cursor_mode')) {
            $cursor = $query->cursorPaginate($perPage)->withQueryString();
            return response()->json([
                'data' => array_map($toHistoryItem, $cursor->items()),
                'meta' => [
                    'next_cursor' => $cursor->nextCursor()?->encode(),
                    'has_more' => $cursor->hasMorePages(),
                    'per_page' => $perPage,
                ],
            ]);
        }

        $page = $query->paginate($perPage);
        $page->setCollection($page->getCollection()->map($toHistoryItem));
        return response()->json(['data' => $page]);
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
