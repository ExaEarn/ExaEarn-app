<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Jobs\ProcessDepositJob;
use App\Jobs\ProcessWithdrawalJob;
use App\Models\AuditLog;
use App\Models\DepositAddress;
use App\Models\Withdrawal;
use App\Repositories\TransactionRepository;
use App\Services\NftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly TransactionRepository $transactionRepo,
        private readonly NftService $nftService,
    ) {
    }

    public function deposit(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'currency' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'tx_hash' => ['required', 'string', 'max:255'],
            'network' => ['required', 'string', 'max:32'],
            'confirmations' => ['required', 'integer', 'min:0'],
            'block_number' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);

        if ($this->transactionRepo->findByTxHash($payload['tx_hash'])) {
            Log::info('Duplicate deposit webhook ignored', ['tx_hash' => $payload['tx_hash']]);

            return response()->json([
                'message' => 'Transaction already processed.',
                'status' => 'duplicate',
            ]);
        }

        $network = strtolower((string) $payload['network']);
        $requiredConfirmations = config("wallet.confirmations.{$network}", 12);

        if ((int) $payload['confirmations'] < $requiredConfirmations) {
            Log::info('Deposit webhook: insufficient confirmations', [
                'tx_hash' => $payload['tx_hash'],
                'confirmations' => $payload['confirmations'],
                'required' => $requiredConfirmations,
            ]);

            return response()->json([
                'message' => 'Insufficient confirmations.',
                'status' => 'pending',
                'confirmations' => $payload['confirmations'],
                'required' => $requiredConfirmations,
            ], 202);
        }

        $addressQuery = DepositAddress::query()
            ->where('user_id', $payload['user_id'])
            ->where('currency', strtoupper((string) $payload['currency']))
            ->where('network', $network);

        $toAddress = data_get($payload, 'metadata.to_address');
        if ($toAddress) {
            $addressQuery->whereRaw('LOWER(address) = ?', [strtolower((string) $toAddress)]);
        }

        $destinationTag = data_get($payload, 'metadata.destination_tag');
        if ($destinationTag !== null) {
            $addressQuery->where('metadata->destination_tag', (int) $destinationTag);
        }

        $addressRecord = $addressQuery->first();

        if (!$addressRecord) {
            Log::warning('Deposit webhook: no matching deposit address', [
                'user_id' => $payload['user_id'],
                'currency' => $payload['currency'],
                'network' => $network,
                'to_address' => $toAddress,
            ]);

            return response()->json([
                'message' => 'Deposit address does not belong to the user.',
                'status' => 'rejected',
            ], 422);
        }

        ProcessDepositJob::dispatch(
            (int) $payload['user_id'],
            strtoupper((string) $payload['currency']),
            (string) $payload['amount'],
            $payload['reference'] ?? null,
            (string) $payload['tx_hash'],
            array_merge($payload['metadata'] ?? [], [
                'network' => $network,
                'confirmations' => $payload['confirmations'],
                'block_number' => $payload['block_number'] ?? null,
            ])
        )->onQueue('deposits');

        AuditLog::create([
            'user_id' => $payload['user_id'],
            'action' => 'deposit_webhook_received',
            'ip_address' => $request->ip(),
            'device' => (string) $request->userAgent(),
            'metadata' => [
                'tx_hash' => $payload['tx_hash'],
                'amount' => $payload['amount'],
                'currency' => $payload['currency'],
                'network' => $network,
                'to_address' => $toAddress,
            ],
        ]);

        return response()->json([
            'message' => 'Deposit accepted for processing.',
            'status' => 'accepted',
        ], 201);
    }

    public function depositAddresses(): JsonResponse
    {
        $addresses = DepositAddress::query()
            ->where('status', 'active')
            ->get([
                'user_id',
                'currency',
                'address',
                'network',
                'metadata',
            ]);

        return response()->json([
            'data' => $addresses,
        ]);
    }

    /**
     * POST /webhooks/treasury-deposits
     * Handle treasury deposit notifications from blockchain watchers.
     */
    public function treasuryDeposit(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'chain' => ['required', 'string', 'max:32'],
            'asset' => ['required', 'string', 'max:16'],
            'from_address' => ['required', 'string', 'max:128'],
            'to_address' => ['required', 'string', 'max:128'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'tx_hash' => ['required', 'string', 'max:128'],
            'block_number' => ['nullable', 'integer'],
            'confirmations' => ['nullable', 'integer'],
        ]);

        // Check if this is a treasury wallet address
        $treasuryWallet = \App\Models\TreasuryWallet::where('address', $payload['to_address'])
            ->where('status', 'active')
            ->first();

        if (!$treasuryWallet) {
            Log::warning('Treasury deposit webhook: address not found in treasury wallets', [
                'to_address' => $payload['to_address'],
                'tx_hash' => $payload['tx_hash'],
            ]);
            return response()->json(['message' => 'Address not recognized as treasury wallet.'], 404);
        }

        // Check for duplicate transaction
        $existing = \App\Models\TreasuryTransaction::where('tx_hash', $payload['tx_hash'])->first();
        if ($existing) {
            Log::info('Duplicate treasury deposit webhook ignored', ['tx_hash' => $payload['tx_hash']]);
            return response()->json(['message' => 'Duplicate transaction.'], 200);
        }

        // Dispatch sweep job if it's a deposit to a non-hot wallet
        if ($treasuryWallet->type !== 'hot') {
            \App\Jobs\SweepFundsJob::dispatch(
                $payload['from_address'],
                (string) $payload['amount'],
                $payload['asset'],
                $payload['chain']
            )->onQueue('treasury');
        }

        // Log the deposit
        \App\Models\TreasuryTransaction::create([
            'type' => 'deposit',
            'chain' => $payload['chain'],
            'currency' => strtoupper($payload['asset']),
            'amount' => $payload['amount'],
            'from_address' => $payload['from_address'],
            'to_address' => $payload['to_address'],
            'tx_hash' => $payload['tx_hash'],
            'status' => 'completed',
            'meta_data' => [
                'block_number' => $payload['block_number'],
                'confirmations' => $payload['confirmations'],
            ],
        ]);

        Log::info('Treasury deposit webhook processed', [
            'tx_hash' => $payload['tx_hash'],
            'amount' => $payload['amount'],
            'asset' => $payload['asset'],
            'chain' => $payload['chain'],
            'to_wallet_type' => $treasuryWallet->type,
        ]);

        return response()->json([
            'message' => 'Treasury deposit processed.',
            'status' => 'accepted',
        ], 201);
    }

    public function withdrawalConfirm(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'transaction_id' => ['required', 'string'],
            'tx_hash' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:completed,failed'],
            'failure_reason' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ]);

        $transaction = $this->transactionRepo->findByTransactionId((string) $payload['transaction_id']);

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found.',
            ], 404);
        }

        $withdrawal = Withdrawal::where('transaction_id', $transaction->id)->first();

        if ($withdrawal) {
            $withdrawal->tx_hash = $payload['tx_hash'] ?? $withdrawal->tx_hash;
            $withdrawal->status = $payload['status'] === 'completed'
                ? TransactionStatus::Completed
                : TransactionStatus::Failed;

            if ($payload['status'] === 'completed') {
                $withdrawal->confirmed_at = now();
            }

            $withdrawal->metadata = array_merge($withdrawal->metadata ?? [], $payload['metadata'] ?? []);
            $withdrawal->save();
        }

        if ($payload['status'] === 'failed') {
            ProcessWithdrawalJob::dispatch(
                (string) $payload['transaction_id'],
                null,
                $payload['failure_reason'] ?? 'Blockchain broadcast failed'
            )->onQueue('withdrawals');
        } else {
            ProcessWithdrawalJob::dispatch(
                (string) $payload['transaction_id'],
                $payload['tx_hash'] ?? null
            )->onQueue('withdrawals');
        }

        AuditLog::create([
            'user_id' => $transaction->user_id,
            'action' => "withdrawal_{$payload['status']}",
            'ip_address' => $request->ip(),
            'device' => (string) $request->userAgent(),
            'metadata' => [
                'transaction_id' => $payload['transaction_id'],
                'tx_hash' => $payload['tx_hash'] ?? null,
                'status' => $payload['status'],
            ],
        ]);

        return response()->json([
            'message' => "Withdrawal {$payload['status']}.",
            'status' => $payload['status'],
        ]);
    }

    public function nftEvent(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'event' => ['required', 'string', 'max:100'],
            'token_id' => ['required', 'integer', 'min:0'],
            'tx_hash' => ['required', 'string', 'max:255'],
            'contract_address' => ['nullable', 'string', 'max:255'],
            'buyer_wallet' => ['nullable', 'string', 'max:255'],
            'seller_wallet' => ['nullable', 'string', 'max:255'],
            'owner_wallet' => ['nullable', 'string', 'max:255'],
            'tier' => ['nullable', 'string', 'max:50'],
            'level' => ['nullable', 'integer', 'min:1'],
            'sale_price_exa' => ['nullable', 'numeric', 'gte:0'],
            'payload' => ['nullable', 'array'],
        ]);

        $synced = $this->nftService->syncBlockchainEvent($payload);

        return response()->json([
            'message' => 'NFT event synced.',
            'data' => $synced,
        ]);
    }

    /**
     * Handle Flutterwave webhook for transfer status updates.
     * Endpoint: POST /webhooks/fiat/flutterwave
     */
    public function flutterwaveWithdrawal(Request $request): JsonResponse
    {
        // Verify webhook signature
        $signature = $request->header('verificationhash');
        if (!$signature) {
            Log::warning('Flutterwave webhook missing signature', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $hash = hash_hmac(
            'sha256',
            json_encode($request->json()->all()),
            config('services.flutterwave.secret_key')
        );

        if (!hash_equals($hash, $signature)) {
            Log::warning('Flutterwave webhook signature mismatch', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->json()->all();

        Log::info('Flutterwave webhook received', [
            'event' => $payload['event'] ?? null,
            'transfer_id' => $payload['data']['id'] ?? null,
        ]);

        // Handle transfer.complete event
        if (($payload['event'] ?? null) === 'transfer.complete') {
            app(\App\Services\FiatWithdrawalService::class)
                ->handleFlutterwaveWebhook($payload);
        }

        return response()->json(['message' => 'Webhook processed'], 200);
    }

    /**
     * Handle Nomba webhook for transfer status updates.
     * Endpoint: POST /webhooks/fiat/nomba
     */
    public function nombaWithdrawal(Request $request): JsonResponse
    {
        // Verify webhook signature
        $signature = $request->header('x-nomba-signature');
        if (!$signature) {
            Log::warning('Nomba webhook missing signature', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = json_encode($request->json()->all());
        $hash = hash_hmac(
            'sha256',
            $payload,
            config('services.nomba.webhook_secret')
        );

        if (!hash_equals($hash, $signature)) {
            Log::warning('Nomba webhook signature mismatch', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->json()->all();

        Log::info('Nomba webhook received', [
            'event_type' => $data['eventType'] ?? null,
            'transaction_id' => $data['data']['transactionId'] ?? null,
        ]);

        // Handle transfer events
        if (in_array($data['eventType'] ?? null, ['transfer.completed', 'transfer.failed', 'transfer.pending'])) {
            app(\App\Services\FiatWithdrawalService::class)
                ->handleNombaWebhook($data);
        }

        return response()->json(['message' => 'Webhook processed'], 200);
    }
}
