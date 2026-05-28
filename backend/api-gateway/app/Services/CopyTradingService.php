<?php

namespace App\Services;

use App\Models\Trader;
use App\Models\CopyRelationship;
use App\Models\User;
use App\Models\FuturesPosition;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CopyTradingService
{
    public function __construct(
        private readonly FuturesOrderService $orderService,
    ) {}

    public function followTrader(int $followerId, int $traderId, float $amountAllocated, string $riskLevel = 'medium'): CopyRelationship
    {
        $trader = Trader::where('id', $traderId)->where('is_master_trader', true)->firstOrFail();

        if ($followerId === $trader->user_id) {
            throw new RuntimeException('Cannot follow yourself');
        }

        // Check if already following
        $existing = CopyRelationship::where('follower_id', $followerId)
            ->where('trader_id', $traderId)
            ->first();

        if ($existing) {
            throw new RuntimeException('Already following this trader');
        }

        DB::transaction(function () use ($followerId, $trader, $amountAllocated, $riskLevel) {
            CopyRelationship::create([
                'follower_id' => $followerId,
                'trader_id' => $trader->id,
                'amount_allocated' => $amountAllocated,
                'risk_level' => $riskLevel,
                'status' => 'active',
            ]);

            $trader->incrementFollowers();
        });

        return CopyRelationship::where('follower_id', $followerId)
            ->where('trader_id', $trader->id)
            ->first();
    }

    public function unfollowTrader(int $followerId, int $traderId): bool
    {
        $relationship = CopyRelationship::where('follower_id', $followerId)
            ->where('trader_id', $traderId)
            ->firstOrFail();

        DB::transaction(function () use ($relationship) {
            $relationship->trader->decrementFollowers();
            $relationship->delete();
        });

        return true;
    }

    public function replicateTrade(int $traderId, array $tradePayload): void
    {
        $relationships = CopyRelationship::where('trader_id', $traderId)
            ->where('status', 'active')
            ->with('follower')
            ->get();

        foreach ($relationships as $relationship) {
            try {
                $this->executeCopyTrade($relationship, $tradePayload);
            } catch (\Exception $e) {
                // Log error but continue with other followers
                \Log::error("Copy trading failed for follower {$relationship->follower_id}: " . $e->getMessage());
            }
        }
    }

    private function executeCopyTrade(CopyRelationship $relationship, array $tradePayload): void
    {
        $proportionalQuantity = $this->calculateProportionalQuantity(
            $tradePayload['quantity'],
            $relationship->amount_allocated,
            $tradePayload['price'] ?? 1
        );

        if ($proportionalQuantity <= 0) {
            return;
        }

        $copyPayload = array_merge($tradePayload, [
            'user_id' => $relationship->follower_id,
            'quantity' => $proportionalQuantity,
        ]);

        $this->orderService->placeOrder($copyPayload);
    }

    private function calculateProportionalQuantity(float $originalQuantity, float $allocatedAmount, float $price): float
    {
        // Simple proportional calculation - can be enhanced
        return ($allocatedAmount / $price) * ($originalQuantity / 100); // Assuming allocatedAmount is in USD
    }

    public function getTraderFollowers(int $traderId): array
    {
        return CopyRelationship::where('trader_id', $traderId)
            ->with('follower')
            ->get()
            ->toArray();
    }

    public function getUserFollowing(int $userId): array
    {
        return CopyRelationship::where('follower_id', $userId)
            ->with('trader.user')
            ->get()
            ->toArray();
    }

    public function updateTraderPerformance(int $traderId, float $performanceScore): void
    {
        Trader::where('id', $traderId)->update(['performance_score' => $performanceScore]);
    }

    public function pauseCopyTrading(int $followerId, int $traderId): bool
    {
        return CopyRelationship::where('follower_id', $followerId)
            ->where('trader_id', $traderId)
            ->update(['status' => 'paused']);
    }

    public function resumeCopyTrading(int $followerId, int $traderId): bool
    {
        return CopyRelationship::where('follower_id', $followerId)
            ->where('trader_id', $traderId)
            ->update(['status' => 'active']);
    }
}