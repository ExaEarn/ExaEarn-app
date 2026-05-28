<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\GiftcardOrder;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Gift Card Buy Fraud Detection Service
 *
 * Analyzes purchase behavior and detects fraud patterns specific to buying.
 */
class GiftCardBuyFraudDetectionService
{
    private const HIGH_RISK_THRESHOLD = 0.7;
    private const MEDIUM_RISK_THRESHOLD = 0.4;
    private const LOW_RISK_THRESHOLD = 0.1;

    private const AUTO_REJECT_THRESHOLD = 0.8;
    private const AUTO_APPROVE_THRESHOLD = 0.2;

    /**
     * Analyze purchase fraud risk.
     *
     * @param User $user
     * @param string $brand
     * @param float $totalAmount
     * @param int $quantity
     * @return array{risk_score: float, risk_level: string, auto_decision: string, flags: array, requires_review: bool}
     */
    public function analyzeRisk(User $user, string $brand, float $totalAmount, int $quantity): array
    {
        $flags = [];
        $scores = [];

        // Check 1: Purchase amount anomalies
        $amountCheck = $this->checkPurchaseAmount($user, $totalAmount);
        if ($amountCheck['flagged']) {
            $flags[] = $amountCheck['flag'];
            $scores[] = $amountCheck['score'];
        }

        // Check 2: Purchase frequency
        $frequencyCheck = $this->checkPurchaseFrequency($user);
        if ($frequencyCheck['flagged']) {
            $flags[] = $frequencyCheck['flag'];
            $scores[] = $frequencyCheck['score'];
        }

        // Check 3: Brand concentration
        $brandCheck = $this->checkBrandConcentration($user, $brand);
        if ($brandCheck['flagged']) {
            $flags[] = $brandCheck['flag'];
            $scores[] = $brandCheck['score'];
        }

        // Check 4: Quantity anomalies
        $quantityCheck = $this->checkQuantityAnomalies($user, $quantity);
        if ($quantityCheck['flagged']) {
            $flags[] = $quantityCheck['flag'];
            $scores[] = $quantityCheck['score'];
        }

        // Check 5: User account history
        $historyCheck = $this->checkUserHistory($user);
        if ($historyCheck['flagged']) {
            $flags[] = $historyCheck['flag'];
            $scores[] = $historyCheck['score'];
        }

        // Calculate final risk score
        $riskScore = $this->calculateRiskScore($scores);
        $riskLevel = $this->determineRiskLevel($riskScore);
        $autoDecision = $this->determineAutoDecision($riskScore, $flags);

        return [
            'risk_score' => round($riskScore, 4),
            'risk_level' => $riskLevel,
            'auto_decision' => $autoDecision,
            'flags' => $flags,
            'requires_review' => !empty($flags) || $autoDecision === 'review',
        ];
    }

    /**
     * Check for unusual purchase amounts.
     *
     * @param User $user
     * @param float $amount
     * @return array
     */
    private function checkPurchaseAmount(User $user, float $amount): array
    {
        // Flag if purchase is unusually large
        if ($amount > 10000) {
            return [
                'flagged' => true,
                'score' => 0.4,
                'flag' => [
                    'type' => 'large_purchase_amount',
                    'description' => "Large purchase amount: \${$amount}",
                ],
            ];
        }

        // Check if amount exceeds user's typical spending
        $avgPurchase = GiftcardOrder::query()
            ->where('user_id', $user->id)
            ->where('type', 'buy')
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subMonth())
            ->avg('amount');

        if ($avgPurchase && $amount > ($avgPurchase * 3)) {
            return [
                'flagged' => true,
                'score' => 0.25,
                'flag' => [
                    'type' => 'unusual_amount_spike',
                    'description' => "Purchase amount \${$amount} is 3x user average (\${$avgPurchase})",
                ],
            ];
        }

        return ['flagged' => false, 'score' => 0, 'flag' => null];
    }

    /**
     * Check for suspicious purchase frequency.
     *
     * @param User $user
     * @return array
     */
    private function checkPurchaseFrequency(User $user): array
    {
        $dailyPurchases = GiftcardOrder::query()
            ->where('user_id', $user->id)
            ->where('type', 'buy')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($dailyPurchases > 20) {
            return [
                'flagged' => true,
                'score' => 0.5,
                'flag' => [
                    'type' => 'high_purchase_frequency_daily',
                    'description' => "High daily purchase frequency: {$dailyPurchases} purchases",
                ],
            ];
        }

        $hourlyPurchases = GiftcardOrder::query()
            ->where('user_id', $user->id)
            ->where('type', 'buy')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($hourlyPurchases > 5) {
            return [
                'flagged' => true,
                'score' => 0.6,
                'flag' => [
                    'type' => 'high_purchase_frequency_hourly',
                    'description' => "High hourly purchase frequency: {$hourlyPurchases} purchases",
                ],
            ];
        }

        return ['flagged' => false, 'score' => 0, 'flag' => null];
    }

    /**
     * Check for brand concentration (buying too much of one brand).
     *
     * @param User $user
     * @param string $brand
     * @return array
     */
    private function checkBrandConcentration(User $user, string $brand): array
    {
        $weeklyBrandTotal = GiftcardOrder::query()
            ->where('user_id', $user->id)
            ->where('type', 'buy')
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subWeek())
            ->sum('amount');

        $weeklyTotal = GiftcardOrder::query()
            ->where('user_id', $user->id)
            ->where('type', 'buy')
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subWeek())
            ->sum('amount');

        if ($weeklyTotal > 0 && ($weeklyBrandTotal / $weeklyTotal) > 0.8) {
            return [
                'flagged' => true,
                'score' => 0.2,
                'flag' => [
                    'type' => 'brand_concentration',
                    'description' => "Brand concentration: {$brand} represents 80%+ of weekly purchases",
                ],
            ];
        }

        return ['flagged' => false, 'score' => 0, 'flag' => null];
    }

    /**
     * Check for quantity anomalies.
     *
     * @param User $user
     * @param int $quantity
     * @return array
     */
    private function checkQuantityAnomalies(User $user, int $quantity): array
    {
        if ($quantity > 100) {
            return [
                'flagged' => true,
                'score' => 0.35,
                'flag' => [
                    'type' => 'unusual_quantity',
                    'description' => "Unusual quantity purchase: {$quantity} cards",
                ],
            ];
        }

        $avgQuantity = GiftcardOrder::query()
            ->where('user_id', $user->id)
            ->where('type', 'buy')
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subMonth())
            ->avg('metadata->quantity') ?? 1;

        if ($quantity > ($avgQuantity * 10)) {
            return [
                'flagged' => true,
                'score' => 0.3,
                'flag' => [
                    'type' => 'quantity_spike',
                    'description' => "Quantity {$quantity} is 10x user average ({$avgQuantity})",
                ],
            ];
        }

        return ['flagged' => false, 'score' => 0, 'flag' => null];
    }

    /**
     * Check user account history.
     *
     * @param User $user
     * @return array
     */
    private function checkUserHistory(User $user): array
    {
        // New account (< 30 days)
        if ($user->created_at > now()->subDays(30)) {
            return [
                'flagged' => true,
                'score' => 0.15,
                'flag' => [
                    'type' => 'new_account',
                    'description' => 'Account created less than 30 days ago',
                ],
            ];
        }

        // No prior successful purchases
        $priorPurchases = GiftcardOrder::query()
            ->where('user_id', $user->id)
            ->where('type', 'buy')
            ->where('status', 'delivered')
            ->count();

        if ($priorPurchases === 0) {
            return [
                'flagged' => true,
                'score' => 0.1,
                'flag' => [
                    'type' => 'first_purchase',
                    'description' => 'First gift card purchase',
                ],
            ];
        }

        return ['flagged' => false, 'score' => 0, 'flag' => null];
    }

    /**
     * Calculate final risk score from individual scores.
     *
     * @param array $scores
     * @return float
     */
    private function calculateRiskScore(array $scores): float
    {
        if (empty($scores)) {
            return 0.0;
        }

        // Use weighted average with higher weights for higher scores
        $weighted = 0;
        $totalWeight = 0;

        foreach ($scores as $score) {
            $weight = 1 + $score; // Higher scores get more weight
            $weighted += $score * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weighted / $totalWeight : 0.0;
    }

    /**
     * Determine risk level from score.
     *
     * @param float $score
     * @return string
     */
    private function determineRiskLevel(float $score): string
    {
        if ($score >= self::HIGH_RISK_THRESHOLD) {
            return 'HIGH';
        }
        if ($score >= self::MEDIUM_RISK_THRESHOLD) {
            return 'MEDIUM';
        }
        if ($score >= self::LOW_RISK_THRESHOLD) {
            return 'LOW';
        }
        return 'VERY_LOW';
    }

    /**
     * Determine auto-decision.
     *
     * @param float $score
     * @param array $flags
     * @return string 'approve'|'reject'|'review'
     */
    private function determineAutoDecision(float $score, array $flags): string
    {
        $config = config('giftcard.fraud_detection');
        $criticalFlags = $config['critical_flags'] ?? ['large_purchase_amount', 'high_purchase_frequency_hourly'];

        // Check for critical flags
        foreach ($flags as $flag) {
            if (in_array($flag['type'], $criticalFlags)) {
                return 'review';
            }
        }

        $rejectThreshold = $config['auto_reject_threshold'] ?? self::AUTO_REJECT_THRESHOLD;
        if ($score >= $rejectThreshold) {
            return 'reject';
        }

        $approveThreshold = $config['auto_approve_threshold'] ?? self::AUTO_APPROVE_THRESHOLD;
        if ($score <= $approveThreshold) {
            return 'approve';
        }

        return 'review';
    }
}
