<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BlockchainService
{
    private string $baseUrl;

    private string $secret;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('wallet.node.url', ''), '/');
        $this->secret = (string) config('wallet.node.secret', '');
        $this->timeout = max(1, (int) config('wallet.node.timeout_seconds', 6));
    }

    public function healthCheck(): array
    {
        return $this->getOrFail('/health');
    }

    public function generateDepositAddress(int $userId, string $currency, string $network): array
    {
        return $this->postOrFail('/addresses/generate', [
            'user_id' => $userId,
            'currency' => strtoupper($currency),
            'network' => strtolower($network),
        ]);
    }

    public function broadcastWithdrawal(string $transactionId, string $currency, string $network, string $toAddress, string $amount): array
    {
        return $this->postOrFail('/withdrawals/broadcast', [
            'transaction_id' => $transactionId,
            'currency' => strtoupper($currency),
            'network' => strtolower($network),
            'to_address' => $toAddress,
            'amount' => $amount,
        ]);
    }

    public function analyzeGiftcardFraud(array $payload): array
    {
        return $this->postOrFail('/fraud/giftcards/analyze', $payload);
    }

    public function distributeReward(string $walletAddress, string $amount, string $token, string $activityType, string $rewardId): array
    {
        return $this->postOrFail('/rewards/distribute', [
            'wallet_address' => $walletAddress,
            'amount' => $amount,
            'token' => strtoupper($token),
            'activity_type' => $activityType,
            'reward_id' => $rewardId,
        ]);
    }

    public function tokenizeFarmProject(array $payload): array
    {
        return $this->postOrFail('/agriculture/tokenize', $payload);
    }

    public function recordFarmInvestment(array $payload): array
    {
        return $this->postOrFail('/agriculture/investments/record', $payload);
    }

    public function registerFarmLease(array $payload): array
    {
        return $this->postOrFail('/agriculture/leases/register', $payload);
    }

    public function distributeAgriReward(array $payload): array
    {
        return $this->postOrFail('/agriculture/rewards/distribute', $payload);
    }

    public function createLotteryRound(array $payload): array
    {
        return $this->postOrFail('/games/lottery/rounds', $payload);
    }

    public function verifyLotteryEntry(array $payload): array
    {
        return $this->postOrFail('/games/lottery/entries/verify', $payload);
    }

    public function fetchLotteryResult(array $payload): array
    {
        return $this->postOrFail('/games/lottery/results/fetch', $payload);
    }

    public function createBettingPool(array $payload): array
    {
        return $this->postOrFail('/games/betting/pools', $payload);
    }

    public function verifyBettingEntry(array $payload): array
    {
        return $this->postOrFail('/games/betting/entries/verify', $payload);
    }

    public function resolveBettingPool(array $payload): array
    {
        return $this->postOrFail('/games/betting/pools/resolve', $payload);
    }

    public function executeContract(string $method, array $params = [], string $contract = 'lottery', string $network = 'base', ?string $value = null): array
    {
        return $this->postNode('/contracts/execute', array_filter([
            'contract' => $contract,
            'method' => $method,
            'params' => $params,
            'network' => $network,
            'value' => $value,
        ], static fn ($item) => $item !== null));
    }

    public function callContract(string $method, array $params = [], string $contract = 'lottery', string $network = 'base'): array
    {
        return $this->postNode('/contracts/call', [
            'contract' => $contract,
            'method' => $method,
            'params' => $params,
            'network' => $network,
        ]);
    }

    public function getTransactionStatus(string $txHash, string $network = 'base'): array
    {
        return $this->getNode('/transactions/'.rawurlencode($txHash).'/status?network='.rawurlencode($network));
    }

    public function publishGameEvent(array $payload): array
    {
        return $this->postOrFail('/games/events/publish', $payload);
    }

    public function moderateP2PMessage(array $payload): array
    {
        return $this->postOrFail('/p2p/chat/moderate', $payload);
    }

    public function publishP2PTradeEvent(array $payload): array
    {
        return $this->postOrFail('/p2p/events/publish', $payload);
    }

    public function mintFinancialNft(array $payload): array
    {
        return $this->postOrFail('/nft/mint', $payload);
    }

    public function verifyFinancialNftOwnership(array $payload): array
    {
        return $this->postOrFail('/nft/ownership/verify', $payload);
    }

    public function createFinancialNftListing(array $payload): array
    {
        return $this->postOrFail('/nft/listings/create', $payload);
    }

    public function buyFinancialNftListing(array $payload): array
    {
        return $this->postOrFail('/nft/listings/buy', $payload);
    }

    public function createFinancialNftAuction(array $payload): array
    {
        return $this->postOrFail('/nft/auctions/create', $payload);
    }

    public function placeFinancialNftBid(array $payload): array
    {
        return $this->postOrFail('/nft/auctions/bid', $payload);
    }

    public function finalizeFinancialNftAuction(array $payload): array
    {
        return $this->postOrFail('/nft/auctions/finalize', $payload);
    }

    public function upgradeFinancialNft(array $payload): array
    {
        return $this->postOrFail('/nft/upgrade', $payload);
    }

    public function submitFuturesOrder(array $payload): array
    {
        return $this->postOrFail('/futures/orders', $payload);
    }

    public function cancelFuturesOrder(array $payload): array
    {
        return $this->postOrFail('/futures/orders/cancel', $payload);
    }

    private function client(): PendingRequest
    {
        if ($this->baseUrl === '' || $this->secret === '') {
            throw new RuntimeException('Blockchain service is not configured.');
        }

        return Http::timeout($this->timeout)
            ->connectTimeout(min(3, $this->timeout))
            ->acceptJson()
            ->withHeaders([
                'X-Service-Secret' => $this->secret,
            ]);
    }

    protected function getNode(string $path): array
    {
        return $this->getOrFail($path);
    }

    protected function postNode(string $path, array $payload): array
    {
        return $this->postOrFail($path, $payload);
    }

    private function getOrFail(string $path): array
    {
        $response = $this->client()->get($this->baseUrl.$path);

        return $this->decode($response, $path);
    }

    private function postOrFail(string $path, array $payload): array
    {
        $response = $this->client()->post($this->baseUrl.$path, $payload);

        return $this->decode($response, $path);
    }

    private function decode(Response $response, string $path): array
    {
        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Blockchain service request failed for %s: %s',
                $path,
                $response->json('error') ?? $response->body()
            ));
        }

        $data = $response->json();

        return is_array($data) ? $data : ['data' => $data];
    }
}
