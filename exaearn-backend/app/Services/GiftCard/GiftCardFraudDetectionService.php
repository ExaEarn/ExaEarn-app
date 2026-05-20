<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\GiftCardFraudFlag;
use App\Models\GiftCardSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class GiftCardFraudDetectionService
{
    private const HIGH_RISK_THRESHOLD = 0.7;
    private const MEDIUM_RISK_THRESHOLD = 0.4;
    private const LOW_RISK_THRESHOLD = 0.2;
    private const RATE_LIMIT_PER_HOUR = 5;
    private const RATE_LIMIT_PER_DAY = 20;
    private const SIMILAR_VALUE_TOLERANCE = 50; // $50
    private const AUTO_REJECT_THRESHOLD = 0.8; // Auto-reject above this
    private const AUTO_APPROVE_THRESHOLD = 0.1; // Auto-approve below this

    /**
     * Perform comprehensive fraud checks with auto-decision logic.
     *
     * @param User $user
     * @param string $brand
     * @param string $cardValue
     * @return array ['risk_score' => decimal, 'risk_level' => string, 'flags' => array, 'auto_decision' => string, 'requires_review' => bool]
     */
    public function analyzeRisk(User $user, string $brand, string $cardValue): array
    {
        $riskScore = 0.0;
        $flags = [];

        // Check rate limiting
        $rateLimitCheck = $this->checkRateLimit($user);
        if ($rateLimitCheck['flagged']) {
            $riskScore += $rateLimitCheck['score'];
            $flags[] = $rateLimitCheck['flag'];
        }

        // Check duplicate patterns
        $duplicateCheck = $this->checkDuplicatePattern($user, $brand, $cardValue);
        if ($duplicateCheck['flagged']) {
            $riskScore += $duplicateCheck['score'];
            $flags[] = $duplicateCheck['flag'];
        }

        // Check account age
        $ageCheck = $this->checkAccountAge($user);
        if ($ageCheck['flagged']) {
            $riskScore += $ageCheck['score'];
            $flags[] = $ageCheck['flag'];
        }

        // Check previous rejections
        $rejectionCheck = $this->checkPreviousRejections($user);
        if ($rejectionCheck['flagged']) {
            $riskScore += $rejectionCheck['score'];
            $flags[] = $rejectionCheck['flag'];
        }

        // Check existing fraud flags
        $existingFraudFlags = $this->checkExistingFlags($user);
        if ($existingFraudFlags['flagged']) {
            $riskScore += $existingFraudFlags['score'];
            $flags[] = $existingFraudFlags['flag'];
        }

        // Check card value anomalies
        $valueCheck = $this->checkCardValueAnomalies($user, $brand, $cardValue);
        if ($valueCheck['flagged']) {
            $riskScore += $valueCheck['score'];
            $flags[] = $valueCheck['flag'];
        }

        // Check user behavior patterns
        $behaviorCheck = $this->checkUserBehaviorPatterns($user);
        if ($behaviorCheck['flagged']) {
            $riskScore += $behaviorCheck['score'];
            $flags[] = $behaviorCheck['flag'];
        }

        // Cap score at 1.0
        $riskScore = min($riskScore, 1.0);

        // Determine risk level and auto-decision
        $riskLevel = $this->determineRiskLevel($riskScore);
        $autoDecision = $this->determineAutoDecision($riskScore, $flags);

        // Log based on risk level
        if ($riskScore >= self::HIGH_RISK_THRESHOLD) {
            Log::warning('High-risk gift card submission detected', [
                'user_id' => $user->id,
                'brand' => $brand,
                'value' => $cardValue,
                'risk_score' => $riskScore,
                'risk_level' => $riskLevel,
                'auto_decision' => $autoDecision,
                'flags_count' => count($flags),
            ]);
        }

        return [
            'risk_score' => number_format($riskScore, 2),
            'risk_level' => $riskLevel,
            'flags' => $flags,
            'auto_decision' => $autoDecision,
            'requires_review' => $autoDecision !== 'approve',
        ];
    }

    /**
     * Create a fraud flag for a user.
     *
     * @param int $userId
     * @param string $flagType
     * @param string $description
     * @param float $score
     * @return GiftCardFraudFlag
     */
    public function createFlag(int $userId, string $flagType, string $description, float $score = 0.0): GiftCardFraudFlag
    {
        return GiftCardFraudFlag::create([
            'user_id' => $userId,
            'flag_type' => $flagType,
            'description' => $description,
            'score' => min($score, 1.0),
            'requires_review' => true,
        ]);
    }

    /**
     * Check rate limiting.
     *
     * @param User $user
     * @return array
     */
    private function checkRateLimit(User $user): array
    {
        $hourlyCount = GiftCardSubmission::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($hourlyCount >= self::RATE_LIMIT_PER_HOUR) {
            return [
                'flagged' => true,
                'score' => 0.2,
                'flag' => [
                    'type' => 'rate_limit_hourly',
                    'description' => "User exceeded hourly submission limit ({$hourlyCount})",
                ],
            ];
        }

        $dailyCount = GiftCardSubmission::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $dailyLimit = (int) config('giftcard.processing.max_daily_submissions_per_user', self::RATE_LIMIT_PER_DAY);
        if ($dailyCount >= $dailyLimit) {
            return [
                'flagged' => true,
                'score' => 0.35,
                'flag' => [
                    'type' => 'rate_limit_daily',
                    'description' => "User exceeded daily submission limit ({$dailyCount})",
                ],
            ];
        }

        return ['flagged' => false, 'score' => 0, 'flag' => null];
    }

    /**
     * Check for duplicate patterns.
     *
     * @param User $user
     * @param string $brand
     * @param string $cardValue
     * @return array
     */
    private function checkDuplicatePattern(User $user, string $brand, string $cardValue): array
    {
        $recentSimilar = GiftCardSubmission::where('user_id', $user->id)
            ->where('brand', $brand)
            ->where('card_value', '>=', (int) $cardValue - self::SIMILAR_VALUE_TOLERANCE)
            ->where('card_value', '<=', (int) $cardValue + self::SIMILAR_VALUE_TOLERANCE)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($recentSimilar > 0) {
            return [
                'flagged' => true,
                'score' => 0.25,
                'flag' => [
                    'type' => 'duplicate_pattern',
                    'description' => "Similar card values detected recently ({$recentSimilar} similar)",
                ],
            ];
        }

        return ['flagged' => false, 'score' => 0, 'flag' => null];
    }

    /**
     * Check account age.
     *
     * @param User $user
     * @return array
     */
    private function checkAccountAge(User $user): array
    {
        $daysOld = $user->created_at->diffInDays(now());
        $hasGiftcardHistory = GiftCardSubmission::where('user_id', $user->id)->exists();

        if (!$hasGiftcardHistory) {
            return ['flagged' => false, 'score' => 0, 'flag' => null];
        }

        if ($daysOld < 7) {
            return [
                'flagged' => true,
                'score' => 0.2,
                'flag' => [
                    'type' => 'new_account',
                    'description' => "Account is only {$daysOld} days old",
                ],
            ];
        }

        return ['flagged' => false, 'score' => 0, 'flag' => null];
    }

    /**
     * Check previous rejections.
     *
     * @param User $user
     * @return array
     */
    private function checkPreviousRejections(User $user): array
    {
        $recentRejections = GiftCardSubmission::where('user_id', $user->id)
            ->where('status', 'rejected')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentRejections >= 2) {
            return [
                'flagged' => true,
                'score' => 0.3,
                'flag' => [
                    'type' => 'multiple_rejections',
                    'description' => "User has {$recentRejections} recent rejections",
                ],
            ];
        }

        return ['flagged' => false, 'score' => 0, 'flag' => null];
    }

    /**
     * Check existing fraud flags.
     *
     * @param User $user
     * @return array
     */
    private function checkExistingFlags(User $user): array
    {
        $unresolvedFlags = GiftCardFraudFlag::where('user_id', $user->id)
            ->unresolved()
            ->get();

        if ($unresolvedFlags->count() > 0) {
            $totalScore = $unresolvedFlags->sum('score') / max($unresolvedFlags->count(), 1);

            return [
                'flagged' => true,
                'score' => $totalScore * 0.1, // Scale down impact
                'flag' => [
                    'type' => 'existing_flags',
                    'description' => "User has {$unresolvedFlags->count()} unresolved fraud flags",
                ],
            ];
        }

        return ['flagged' => false, 'score' => 0, 'flag' => null];
    }

    /**
     * Check for card value anomalies.
     *
     * @param User $user
     * @param string $brand
     * @param string $cardValue
     * @return array
     */
    private function checkCardValueAnomalies(User $user, string $brand, string $cardValue): array
    {
        $cardValueFloat = (float) $cardValue;

        // Check for round number patterns (suspicious)
        if ($cardValueFloat % 100 === 0 && $cardValueFloat >= 500) {
            return [
                'flagged' => true,
                'score' => 0.15,
                'flag' => [
                    'type' => 'round_number_value',
                    'description' => "Large round number value: \${$cardValue}",
                ],
            ];
        }

        // Check for unusually high values
        if ($cardValueFloat > 1000) {
            return [
                'flagged' => true,
                'score' => 0.2,
                'flag' => [
                    'type' => 'high_value_card',
                    'description' => "Unusually high card value: \${$cardValue}",
                ],
            ];
        }

        // Check for very low values (potential test cards)
        if ($cardValueFloat < 10) {
            return [
                'flagged' => true,
                'score' => 0.1,
                'flag' => [
                    'type' => 'low_value_card',
                    'description' => "Very low card value: \${$cardValue}",
                ],
            ];
        }

        return ['flagged' => false, 'score' => 0, 'flag' => null];
    }

    /**
     * Check user behavior patterns.
     *
     * @param User $user
     * @return array
     */
    private function checkUserBehaviorPatterns(User $user): array
    {
        // Check submission frequency patterns
        $lastWeekSubmissions = GiftCardSubmission::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subWeek())
            ->count();

        if ($lastWeekSubmissions > 10) {
            return [
                'flagged' => true,
                'score' => 0.25,
                'flag' => [
                    'type' => 'high_frequency_submissions',
                    'description' => "High submission frequency: {$lastWeekSubmissions} in last week",
                ],
            ];
        }

        // Check for submissions at unusual hours
        $currentHour = now()->hour;
        if ($currentHour < 6 || $currentHour > 22) {
            return [
                'flagged' => true,
                'score' => 0.05,
                'flag' => [
                    'type' => 'unusual_submission_time',
                    'description' => "Submission at unusual hour: {$currentHour}:00",
                ],
            ];
        }

        return ['flagged' => false, 'score' => 0, 'flag' => null];
    }

    /**
     * Determine risk level based on score.
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
     * Determine auto-decision based on risk score and flags.
     *
     * @param float $score
     * @param array $flags
     * @return string 'approve'|'reject'|'review'
     */
    private function determineAutoDecision(float $score, array $flags): string
    {
        $config = config('giftcard.fraud_detection');
        $criticalFlags = $config['critical_flags'] ?? ['multiple_rejections', 'high_frequency_submissions', 'existing_flags'];

        // Auto-reject for very high risk
        $rejectThreshold = $config['auto_reject_threshold'] ?? self::AUTO_REJECT_THRESHOLD;
        if ($score >= $rejectThreshold) {
            return 'reject';
        }

        // Auto-approve for very low risk
        $approveThreshold = $config['auto_approve_threshold'] ?? self::AUTO_APPROVE_THRESHOLD;
        if ($score <= $approveThreshold && empty($flags)) {
            return 'approve';
        }

        // Check for critical flags that require review
        foreach ($flags as $flag) {
            if (in_array($flag['type'], $criticalFlags)) {
                return 'review';
            }
        }

        // Review for medium to high risk
        $reviewThreshold = $config['review_threshold'] ?? self::AUTO_APPROVE_THRESHOLD;
        if ($score >= $reviewThreshold) {
            return 'review';
        }

        // Default to approve for low risk
        return 'approve';
    }
}
