<?php

declare(strict_types=1);

namespace App\Domain\P2P\Services;

use App\Models\P2PDispute;
use App\Models\P2PRating;
use App\Models\P2PTrade;
use App\Models\User;
use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;

class P2PReputationService
{
    public const VERSION = 'p2p-reputation-v1';

    public function snapshot(User|int $user): array
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;
        $totalOrders = P2PTrade::query()
            ->where(fn ($query) => $query->where('buyer_id', $userId)->orWhere('seller_id', $userId))
            ->count();
        $completedOrders = P2PTrade::query()
            ->where(fn ($query) => $query->where('buyer_id', $userId)->orWhere('seller_id', $userId))
            ->where('status', 'released')
            ->count();
        $disputes = P2PDispute::query()
            ->whereHas('trade', fn ($query) => $query->where('buyer_id', $userId)->orWhere('seller_id', $userId))
            ->count();
        $avgRating = P2PRating::query()->where('rated_user_id', $userId)->avg('score');

        $completionRate = $totalOrders > 0 ? FinancialDecimal::div((string) $completedOrders, (string) $totalOrders, 8) : '0';
        $disputeRate = $totalOrders > 0 ? FinancialDecimal::div((string) $disputes, (string) $totalOrders, 8) : '0';
        $ratingFactor = $avgRating ? FinancialDecimal::div((string) $avgRating, '5', 8) : '0.5';
        $score = FinancialDecimal::mul('100', FinancialDecimal::max('0', FinancialDecimal::sub(FinancialDecimal::add($completionRate, $ratingFactor, 8), $disputeRate, 8), 8), 4);

        $factors = [
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'completion_rate' => $completionRate,
            'dispute_count' => $disputes,
            'dispute_rate' => $disputeRate,
            'average_rating' => $avgRating ? round((float) $avgRating, 2) : null,
        ];

        DB::table('p2p_reputation_snapshots')->insert([
            'user_id' => $userId,
            'score_version' => self::VERSION,
            'score' => $score,
            'factors' => json_encode($factors, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('p2p_merchant_profiles')->where('user_id', $userId)->update([
            'reputation_score' => $score,
            'completed_orders' => $completedOrders,
            'completion_rate' => FinancialDecimal::mul($completionRate, '100', 4),
            'dispute_rate' => FinancialDecimal::mul($disputeRate, '100', 4),
            'updated_at' => now(),
        ]);

        return [
            'score_version' => self::VERSION,
            'score' => $score,
            'factors' => $factors,
        ];
    }
}
