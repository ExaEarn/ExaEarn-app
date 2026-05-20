<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTradingProfile;
use App\Models\TradingAuditLog;

class UserProfileAI
{
    /**
     * Initialize trading profile for a new user
     */
    public function initializeProfile(User $user, array $data = []): UserTradingProfile
    {
        // Default profile for new users
        $defaults = [
            'skill_level' => 'beginner',
            'risk_tolerance' => 'medium',
            'preferred_leverage' => 1,
            'max_position_size' => 0,
            'daily_loss_limit' => 0,
            'account_balance' => $data['account_balance'] ?? 10000,
            'total_trading_experience_months' => $data['experience_months'] ?? 0,
            'preferred_symbols' => $data['preferred_symbols'] ?? ['BTCUSD', 'ETHUSD'],
            'preferred_strategies' => $data['preferred_strategies'] ?? [],
            'enable_ai_suggestions' => true,
            'enable_auto_trading' => false,
            'ai_trade_mode' => 'assist',
            'auto_trading_max_drawdown' => 20,
        ];

        return $user->tradingProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $defaults
        );
    }

    /**
     * Classify user skill level based on trading history
     */
    public function classifySkillLevel(User $user): string
    {
        // Analyze trading history
        $tradesCount = $user->auditLogs()->count();
        $totalExperience = $user->tradingProfile?->total_trading_experience_months ?? 0;

        // Get success metrics
        $auditLogs = $user->auditLogs()
            ->latest()
            ->limit(50)
            ->get();

        $successCount = 0;
        $winRate = 0;

        if ($auditLogs->count() > 0) {
            foreach ($auditLogs as $log) {
                $result = $log->result ?? [];
                if (isset($result['pnl']) && $result['pnl'] > 0) {
                    $successCount++;
                }
            }
            $winRate = ($successCount / $auditLogs->count()) * 100;
        }

        // Classification logic
        if ($tradesCount < 10 || $totalExperience < 1) {
            return 'beginner';
        }

        if ($tradesCount < 50 || $totalExperience < 6) {
            if ($winRate > 60) {
                return 'intermediate';
            }
            return 'beginner';
        }

        // Over 50 trades or 6 months
        if ($winRate > 55 && $tradesCount > 100) {
            return 'advanced';
        }

        if ($winRate > 50) {
            return 'intermediate';
        }

        return 'beginner';
    }

    /**
     * Update risk tolerance based on trading behavior
     */
    public function updateRiskTolerance(User $user): string
    {
        $profile = $user->tradingProfile;

        if (!$profile) {
            return 'medium';
        }

        // Get recent positions and losses
        $auditLogs = $user->auditLogs()
            ->whereDate('created_at', '>=', now()->subDays(30))
            ->get();

        $totalLoss = 0;
        $maxDrawdown = 0;
        $tradeCount = 0;

        foreach ($auditLogs as $log) {
            $result = $log->result ?? [];
            $pnl = $result['pnl'] ?? 0;

            if ($pnl < 0) {
                $totalLoss += abs($pnl);
                $maxDrawdown = max($maxDrawdown, abs($pnl));
            }
            $tradeCount++;
        }

        if ($tradeCount === 0) {
            return 'medium';
        }

        $avgLossPerTrade = $totalLoss / $tradeCount;
        $riskFactor = ($avgLossPerTrade / ($profile->account_balance ?? 10000)) * 100;

        // Adjust risk tolerance based on behavior
        if ($riskFactor < 0.5) {
            return 'low';  // Being very conservative
        }

        if ($riskFactor > 2) {
            return 'high';  // Taking larger risks
        }

        return 'medium';
    }

    /**
     * Adjust leverage recommendations based on performance
     */
    public function getRecommendedLeverage(User $user): int
    {
        $profile = $user->tradingProfile;

        if (!$profile) {
            return 1;
        }

        $skillLevel = $this->classifySkillLevel($user);
        $riskTolerance = $this->updateRiskTolerance($user);

        $baseLeverage = match ($skillLevel) {
            'beginner' => 1,
            'intermediate' => 2,
            'advanced' => 5,
            default => 1,
        };

        $toleranceMultiplier = match ($riskTolerance) {
            'low' => 0.5,
            'medium' => 1.0,
            'high' => 1.5,
            default => 1.0,
        };

        $recommended = (int)($baseLeverage * $toleranceMultiplier);

        // Cap at user preference
        return min($recommended, $profile->preferred_leverage);
    }

    /**
     * Get personalized AI suggestions based on profile
     */
    public function getPersonalizedSuggestions(User $user, array $signal = [], array $currentPositions = []): array
    {
        $profile = $user->tradingProfile;

        if (!$profile) {
            return [];
        }

        $skillLevel = $profile->skill_level;
        $suggestions = [];

        if ($skillLevel === 'beginner') {
            $suggestions[] = [
                'id' => 'risk_education',
                'title' => 'Position Sizing Matters',
                'description' => 'Risk only 0.5-1% per trade. This is more important than picking winners.',
                'priority' => 'high',
            ];

            $suggestions[] = [
                'id' => 'stop_loss_essential',
                'title' => 'Always Use Stop Losses',
                'description' => 'Protect your capital with stop losses on every trade.',
                'priority' => 'high',
            ];

            $suggestions[] = [
                'id' => 'leverage_warning',
                'title' => 'Start Without Leverage',
                'description' => 'Build skills with spot trading before using leverage.',
                'priority' => 'medium',
            ];
        } elseif ($skillLevel === 'intermediate') {
            if (!empty($signal) && $signal['confidence'] > 75) {
                $suggestions[] = [
                    'id' => 'high_confidence_signal',
                    'title' => 'Strong Signal Detected',
                    'description' => "Signal confidence: {$signal['confidence']}%. Consider scaling in.",
                    'priority' => 'high',
                ];
            }

            if (!empty($currentPositions) && count($currentPositions) > 3) {
                $suggestions[] = [
                    'id' => 'over_position',
                    'title' => 'Multiple Positions Open',
                    'description' => 'You have ' . count($currentPositions) . ' open positions. Consider closing some.',
                    'priority' => 'medium',
                ];
            }
        } else {
            // Advanced
            $suggestions[] = [
                'id' => 'strategy_optimization',
                'title' => 'Advanced Strategies Available',
                'description' => 'Consider using grid trading or DCA for optimal entries.',
                'priority' => 'medium',
            ];

            if (!empty($signal) && $signal['confidence'] > 80) {
                $suggestions[] = [
                    'id' => 'trailing_stop_setup',
                    'title' => 'Trail Your Winners',
                    'description' => "This signal has strong momentum. Consider a trailing stop.",
                    'priority' => 'high',
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Track learning progress and provide feedback
     */
    public function getLearningFeedback(User $user): array
    {
        $auditLogs = $user->auditLogs()
            ->whereDate('created_at', '>=', now()->subDays(30))
            ->get();

        if ($auditLogs->count() === 0) {
            return [
                'stage' => 'no_activity',
                'message' => 'Start by reviewing a signal or placing a trade.',
                'tips' => [
                    'Review the education section',
                    'Start with a demo trade',
                    'Study existing positions',
                ],
            ];
        }

        // Analyze behavior
        $followedAI = 0;
        $contraAI = 0;
        $ignoreAI = 0;
        $totalPnL = 0;

        foreach ($auditLogs as $log) {
            $aiSuggestion = $log->ai_suggestion ?? [];
            $userAction = $log->user_action ?? [];
            $result = $log->result ?? [];

            if (!empty($aiSuggestion) && !empty($userAction)) {
                if ($this->didFollowSuggestion($aiSuggestion, $userAction)) {
                    $followedAI++;
                } else {
                    $contraAI++;
                }
            } else {
                $ignoreAI++;
            }

            $totalPnL += $result['pnl'] ?? 0;
        }

        $followRate = $followedAI > 0 ? ($followedAI / ($followedAI + $contraAI)) * 100 : 0;

        $feedback = [
            'stage' => 'active',
            'trades_analyzed' => $auditLogs->count(),
            'ai_follow_rate' => (int)$followRate,
            'total_pnl' => round($totalPnL, 2),
            'message' => '',
            'recommendations' => [],
        ];

        if ($followRate > 70) {
            $feedback['message'] = 'Great job following signals! Keep it up.';
            $feedback['recommendations'][] = 'Try increasing position size gradually';
        } elseif ($followRate > 40) {
            $feedback['message'] = 'Good start. Trust the signals more often.';
            $feedback['recommendations'][] = 'Review why you deviated from signals';
        } else {
            $feedback['message'] = 'You\'re deviating from signals. Trust the process.';
            $feedback['recommendations'][] = 'Review signal track record';
            $feedback['recommendations'][] = 'Start with smaller position sizes';
        }

        if ($totalPnL > 0) {
            $feedback['message'] .= ' You\'re profitable! 🎉';
        } else {
            $feedback['message'] .= ' Focus on risk management.';
            $feedback['recommendations'][] = 'Reduce position size';
            $feedback['recommendations'][] = 'Use tighter stop losses';
        }

        return $feedback;
    }

    private function didFollowSuggestion(array $suggestion, array $action): bool
    {
        $suggestionSide = $suggestion['type'] ?? null;
        $actionSide = $action['side'] ?? null;

        if (!$suggestionSide || !$actionSide) {
            return false;
        }

        return strtolower($suggestionSide) === strtolower($actionSide);
    }

    /**
     * Get next skill level recommendations
     */
    public function getSkillProgression(User $user): array
    {
        $profile = $user->tradingProfile;
        $currentSkill = $profile?->skill_level ?? 'beginner';

        $progression = [
            'beginner' => [
                'current' => 'Beginner',
                'requirements' => '10+ trades, 1+ month experience',
                'next' => 'Intermediate',
                'tips' => [
                    'Master position sizing',
                    'Achieve 50%+ win rate',
                    'Document every trade',
                    'Study chart patterns',
                ],
            ],
            'intermediate' => [
                'current' => 'Intermediate',
                'requirements' => '50+ trades, 6+ months experience',
                'next' => 'Advanced',
                'tips' => [
                    'Develop a consistent strategy',
                    'Master risk management',
                    'Achieve 55%+ win rate',
                    'Use leverage responsibly',
                ],
            ],
            'advanced' => [
                'current' => 'Advanced',
                'requirements' => 'Already at max level',
                'next' => null,
                'tips' => [
                    'Mentor other traders',
                    'Develop automated strategies',
                    'Diversify across asset classes',
                    'Optimize for consistency',
                ],
            ],
        ];

        return $progression[$currentSkill] ?? $progression['beginner'];
    }
}
