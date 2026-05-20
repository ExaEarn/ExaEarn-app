<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Models\Nft;
use App\Models\Transaction;
use App\Services\RealtimeStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BlockchainEventController extends Controller
{
    public function __construct(private readonly RealtimeStreamService $stream)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'event' => ['required', 'string', 'max:80'],
            'txHash' => ['nullable', 'string', 'max:255'],
            'tx_hash' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'wallet_address' => ['nullable', 'string', 'max:128'],
            'network' => ['nullable', 'string', 'max:40'],
            'round_id' => ['nullable', 'integer'],
            'token_id' => ['nullable'],
            'block_number' => ['nullable', 'integer'],
            'contract_address' => ['nullable', 'string', 'max:128'],
            'amount_wei' => ['nullable', 'string', 'max:100'],
            'jackpot_wei' => ['nullable', 'string', 'max:100'],
            'utility_type' => ['nullable', 'string', 'max:80'],
            'tier' => ['nullable', 'string', 'max:80'],
            'metadata_uri' => ['nullable', 'string', 'max:2048'],
            'mint_fee_wei' => ['nullable', 'string', 'max:100'],
        ]);

        $txHash = $payload['txHash'] ?? $payload['tx_hash'] ?? null;

        if ($txHash) {
            $this->markTransactionConfirmed($txHash, $payload);
        }

        if ($payload['event'] === 'NFTMinted') {
            $this->syncMintedNft($payload, $txHash);
        }

        $this->publishRealtimeUpdate($payload, $txHash);

        Log::info('Blockchain event processed', [
            'event' => $payload['event'],
            'tx_hash' => $txHash,
            'network' => $payload['network'] ?? null,
        ]);

        return response()->json([
            'status' => 'accepted',
            'event' => $payload['event'],
            'tx_hash' => $txHash,
        ], 202);
    }

    private function markTransactionConfirmed(string $txHash, array $payload): void
    {
        $transaction = Transaction::query()->where('tx_hash', $txHash)->first();
        if (!$transaction || !in_array($transaction->status->value ?? $transaction->status, [
            TransactionStatus::Pending->value,
            TransactionStatus::Processing->value,
        ], true)) {
            return;
        }

        $transaction->status = TransactionStatus::Completed;
        $transaction->metadata = array_merge($transaction->metadata ?? [], [
            'blockchain_event' => $payload,
        ]);
        $transaction->save();
    }

    private function syncMintedNft(array $payload, ?string $txHash): void
    {
        if (!$txHash || empty($payload['token_id'])) {
            return;
        }

        Nft::query()
            ->where('mint_tx_hash', $txHash)
            ->update([
                'token_id' => (string) $payload['token_id'],
                'last_event_tx_hash' => $txHash,
                'last_synced_at' => now(),
            ]);
    }

    private function publishRealtimeUpdate(array $payload, ?string $txHash): void
    {
        $userId = $payload['user_id'] ?? null;
        if (!$userId) {
            $transaction = $txHash ? Transaction::query()->where('tx_hash', $txHash)->first() : null;
            $userId = $transaction?->user_id;
        }

        if (!$userId) {
            return;
        }

        $this->stream->publishPayload('portfolio_updates', [
            'event' => 'transaction.blockchain_updated',
            'user_id' => $userId,
            'data' => $payload,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
